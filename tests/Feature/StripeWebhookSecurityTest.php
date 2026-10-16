<?php

namespace Tests\Feature;

use App\Contracts\SubscriptionGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Cashier\Events\WebhookHandled;
use Mockery\MockInterface;
use Stripe\Exception\ApiConnectionException;
use Tests\TestCase;

class StripeWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cashier.webhook.secret' => 'whsec_security']);
        User::factory()->create()->currentTeam->forceFill(['stripe_id' => 'cus_secure'])->save();
    }

    public function test_duplicate_event_is_processed_only_once(): void
    {
        $this->mock(SubscriptionGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('subscriptionSnapshot')->once()->with('sub_secure')->andReturn($this->snapshot());
        });
        $this->webhook('evt_same')->assertOk();
        $this->webhook('evt_same')->assertOk();
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('stripe_webhook_receipts', 1);
    }

    public function test_different_events_with_the_same_timestamp_are_both_reconciled(): void
    {
        $this->mock(SubscriptionGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('subscriptionSnapshot')->twice()->andReturn($this->snapshot());
        });
        $this->webhook('evt_first')->assertOk();
        $this->webhook('evt_second')->assertOk();
        $this->assertDatabaseCount('stripe_webhook_receipts', 2);
    }

    public function test_stripe_outage_does_not_grant_access_or_mark_the_event_as_processed(): void
    {
        $attempt = 0;
        $this->mock(SubscriptionGateway::class)->shouldReceive('subscriptionSnapshot')->twice()
            ->andReturnUsing(function () use (&$attempt): array {
                if ($attempt++ === 0) {
                    throw new ApiConnectionException('Connection unavailable');
                }

                return $this->snapshot();
            });
        $this->webhook('evt_retry')->assertStatus(503);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('stripe_webhook_receipts', 0);

        $this->webhook('evt_retry')->assertOk();
        $this->assertDatabaseCount('stripe_webhook_receipts', 1);
    }

    public function test_current_subscription_must_belong_to_the_event_customer(): void
    {
        $this->mock(SubscriptionGateway::class)->shouldReceive('subscriptionSnapshot')->once()
            ->andReturn($this->snapshot(['customer' => 'cus_other']));
        $this->webhook('evt_wrong_customer')->assertStatus(503);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('stripe_webhook_receipts', 0);
    }

    public function test_unknown_customers_do_not_trigger_stripe_queries(): void
    {
        $this->mock(SubscriptionGateway::class)->shouldNotReceive('subscriptionSnapshot');
        $this->webhook('evt_unknown', customer: 'cus_unknown')->assertOk();
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_late_created_event_uses_current_cancelled_state(): void
    {
        $this->mock(SubscriptionGateway::class)->shouldReceive('subscriptionSnapshot')->once()
            ->andReturn($this->snapshot(['status' => 'canceled', 'canceled_at' => time()]));
        $this->webhook('evt_late_created', type: 'customer.subscription.created')->assertOk();
        $this->assertDatabaseHas('subscriptions', ['stripe_id' => 'sub_secure', 'stripe_status' => 'canceled']);
    }

    public function test_cancellation_and_period_use_the_authoritative_snapshot(): void
    {
        $end = now()->addDays(7)->startOfSecond();
        $this->mock(SubscriptionGateway::class)->shouldReceive('subscriptionSnapshot')->once()
            ->andReturn($this->snapshot(['cancel_at_period_end' => true, 'cancel_at' => $end->timestamp]));
        $this->webhook('evt_scheduled')->assertOk();
        $this->assertDatabaseHas('subscriptions', ['stripe_id' => 'sub_secure', 'ends_at' => $end->format('Y-m-d H:i:s')]);
    }

    public function test_processing_failure_rolls_back_subscription_and_receipt(): void
    {
        $this->mock(SubscriptionGateway::class)->shouldReceive('subscriptionSnapshot')->once()->andReturn($this->snapshot());
        $this->app['events']->listen(WebhookHandled::class, function (): void {
            throw new \RuntimeException('Simulated database processing failure');
        });
        $this->webhook('evt_failure')->assertServerError();
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('subscription_items', 0);
        $this->assertDatabaseCount('stripe_webhook_receipts', 0);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function snapshot(array $overrides = []): array
    {
        return array_replace([
            'id' => 'sub_secure', 'customer' => 'cus_secure', 'status' => 'active', 'trial_end' => null,
            'metadata' => ['type' => 'default'],
            'items' => ['data' => [[
                'id' => 'si_secure', 'quantity' => 1, 'current_period_end' => now()->addMonth()->timestamp,
                'price' => ['id' => 'price_monthly', 'product' => 'prod_secure'],
            ]]],
        ], $overrides);
    }

    private function webhook(string $id, string $type = 'customer.subscription.updated', string $customer = 'cus_secure'): TestResponse
    {
        $payload = json_encode([
            'id' => $id, 'type' => $type, 'created' => 1000,
            'data' => ['object' => ['id' => 'sub_secure', 'customer' => $customer, 'status' => 'active']],
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();

        return $this->call('POST', route('cashier.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_security'),
        ], $payload);
    }
}
