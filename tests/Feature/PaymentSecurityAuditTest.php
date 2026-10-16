<?php

namespace Tests\Feature;

use App\Contracts\SubscriptionGateway;
use App\Enums\OrderModality;
use App\Enums\TeamRole;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cashier.key' => 'pk_test_audit',
            'cashier.secret' => 'sk_test_audit',
            'cashier.webhook.secret' => 'whsec_audit',
            'billing.plans.monthly.price_id' => 'price_monthly',
        ]);
    }

    public function test_billing_mutations_require_authentication(): void
    {
        $owner = User::factory()->create();
        $this->mock(SubscriptionGateway::class)->shouldNotReceive('createCheckout', 'billingPortal');

        foreach (['billing.checkout', 'billing.portal'] as $route) {
            $this->postJson(route($route, $owner->currentTeam->slug), ['plan' => 'monthly'])->assertUnauthorized();
        }
    }

    public function test_owning_another_team_does_not_grant_billing_access_here(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $other->currentTeam->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $this->mock(SubscriptionGateway::class)->shouldNotReceive('createCheckout', 'billingPortal');

        foreach (['billing.checkout', 'billing.portal'] as $route) {
            $this->actingAs($owner)->postJson(route($route, $other->currentTeam->slug), ['plan' => 'monthly'])->assertForbidden();
        }
    }

    public function test_checkout_and_portal_reject_cross_site_requests_without_csrf_token(): void
    {
        $owner = User::factory()->create();
        $this->app->instance('env', 'local');
        try {
            foreach (['billing.checkout', 'billing.portal'] as $route) {
                $this->actingAs($owner)->withHeader('Sec-Fetch-Site', 'cross-site')
                    ->postJson(route($route, $owner->currentTeam->slug), ['plan' => 'monthly'])
                    ->assertStatus(419);
            }
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_billing_requests_are_rate_limited(): void
    {
        $owner = User::factory()->create();
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($owner)->postJson(route('billing.checkout', $owner->currentTeam->slug), ['plan' => 'invalid'])
                ->assertUnprocessable();
        }
        $this->actingAs($owner)->postJson(route('billing.checkout', $owner->currentTeam->slug), ['plan' => 'invalid'])
            ->assertTooManyRequests();
    }

    public function test_modified_webhook_body_and_expired_signature_are_rejected(): void
    {
        $payload = json_encode(['id' => 'evt_audit', 'type' => 'customer.subscription.created'], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_audit');
        $this->call('POST', route('cashier.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload.' ')->assertForbidden();

        $timestamp = time() - 600;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_audit');
        $this->call('POST', route('cashier.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload)->assertForbidden();
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_order_price_is_not_taken_from_client_input(): void
    {
        $owner = User::factory()->create();
        $product = Product::factory()->create(['team_id' => $owner->current_team_id, 'price' => '18.00']);
        $this->actingAs($owner)->postJson(route('orders.store'), [
            'modality' => OrderModality::DineIn->value,
            'payment_method' => 'pix',
            'total' => '0.01',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '0.01']],
        ])->assertCreated();

        $this->assertSame('36.00', Order::query()->firstOrFail()->total());
        $this->assertDatabaseHas('order_items', ['unit_price' => '18.00', 'quantity' => 2]);
    }

    public function test_cancelled_subscription_prevents_new_orders(): void
    {
        config(['billing.enforce_subscriptions' => true]);
        $owner = User::factory()->create();
        $owner->currentTeam->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_cancelled', 'stripe_status' => 'canceled', 'ends_at' => now()->subDay(),
        ]);
        $product = Product::factory()->create(['team_id' => $owner->current_team_id]);

        $this->actingAs($owner)->postJson(route('orders.store'), [
            'modality' => OrderModality::DineIn->value, 'payment_method' => 'pix',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(402);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_old_signed_update_cannot_restore_a_cancelled_subscription(): void
    {
        $owner = User::factory()->create();
        $owner->currentTeam->forceFill(['stripe_id' => 'cus_audit'])->save();
        $data = [
            'id' => 'sub_audit', 'customer' => 'cus_audit', 'status' => 'active', 'trial_end' => null,
            'metadata' => ['type' => 'default'],
            'items' => ['data' => [[
                'id' => 'si_audit', 'quantity' => 1, 'current_period_end' => now()->addMonth()->timestamp,
                'price' => ['id' => 'price_monthly', 'product' => 'prod_audit'],
            ]]],
        ];
        $snapshot = $data;
        $this->mock(SubscriptionGateway::class)->shouldReceive('subscriptionSnapshot')
            ->andReturnUsing(function () use (&$snapshot): array {
                return $snapshot;
            });
        $this->webhook('customer.subscription.created', $data, time() - 120)->assertOk();
        $snapshot['status'] = 'canceled';
        $snapshot['canceled_at'] = time();
        $this->webhook('customer.subscription.deleted', ['id' => 'sub_audit', 'customer' => 'cus_audit'], time())->assertOk();
        $this->assertDatabaseHas('subscriptions', ['stripe_id' => 'sub_audit', 'stripe_status' => 'canceled']);

        $this->webhook('customer.subscription.updated', $data, time() - 60)->assertOk();
        $this->assertDatabaseHas('subscriptions', ['stripe_id' => 'sub_audit', 'stripe_status' => 'canceled']);
        $this->assertNotNull($owner->currentTeam->fresh()->subscription('default')->ends_at);
    }

    public function test_cash_rejects_amounts_above_the_database_decimal_limit(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson(route('cash-registers.open'), ['opening_balance' => '100000000.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('opening_balance');

        $this->postJson(route('cash-registers.open'), ['opening_balance' => '10.00'])->assertCreated();

        $register = CashRegister::query()->firstOrFail();
        $this->postJson(route('cash-registers.movements.store', $register), [
            'type' => 'suprimento', 'amount' => '100000000.00', 'description' => 'Limite de valor — auditoria',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->patchJson(route('cash-registers.close', $register), ['closing_balance' => '100000000.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('closing_balance');
        $this->patchJson(route('cash-registers.close', $register), ['closing_balance' => '-100000000.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('closing_balance');
        $this->assertDatabaseCount('cash_movements', 0);
        $this->assertNull($register->fresh()->closed_at);
    }

    /** @param array<string, mixed> $data */
    private function webhook(string $type, array $data, int $created): TestResponse
    {
        $payload = json_encode(['id' => 'evt_'.str_replace('.', '_', $type), 'type' => $type, 'created' => $created, 'data' => ['object' => $data]], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_audit');

        return $this->call('POST', route('cashier.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload);
    }
}
