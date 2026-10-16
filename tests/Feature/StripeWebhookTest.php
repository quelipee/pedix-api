<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_is_disabled_when_the_signing_secret_is_missing(): void
    {
        config(['cashier.webhook.secret' => null]);

        $this->postJson(route('cashier.webhook'), ['type' => 'customer.subscription.created'])->assertStatus(503);
    }

    public function test_unsigned_or_forged_webhooks_are_rejected(): void
    {
        config(['cashier.webhook.secret' => 'whsec_example']);

        $this->postJson(route('cashier.webhook'), ['type' => 'customer.subscription.created'])->assertForbidden();
        $this->signedWebhook($this->subscriptionEvent(), 'incorrect_secret')->assertForbidden();
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_valid_webhook_creates_the_subscription_on_the_correct_team_without_duplicates(): void
    {
        config(['cashier.webhook.secret' => 'whsec_example']);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->currentTeam->forceFill(['stripe_id' => 'cus_pedix'])->save();

        $payload = $this->subscriptionEvent();
        $this->signedWebhook($payload)->assertOk();
        $this->signedWebhook($payload)->assertOk();

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('subscription_items', 1);
        $this->assertDatabaseHas('subscriptions', [
            'team_id' => $owner->current_team_id, 'stripe_id' => 'sub_pedix', 'stripe_status' => 'trialing', 'stripe_price' => 'price_monthly',
        ]);
        $this->assertSame(0, $other->currentTeam->subscriptions()->count());
        $this->assertTrue($owner->currentTeam->fresh()->subscribed());
    }

    public function test_stripe_cancellation_is_synchronized_locally(): void
    {
        config(['cashier.webhook.secret' => 'whsec_example']);
        $owner = User::factory()->create();
        $owner->currentTeam->forceFill(['stripe_id' => 'cus_pedix'])->save();
        $this->signedWebhook($this->subscriptionEvent())->assertOk();

        $this->signedWebhook([
            'id' => 'evt_deleted',
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['id' => 'sub_pedix', 'customer' => 'cus_pedix']],
        ])->assertOk();

        $this->assertDatabaseHas('subscriptions', ['stripe_id' => 'sub_pedix', 'stripe_status' => 'canceled']);
        $this->assertFalse($owner->currentTeam->fresh()->subscribed());
    }

    /** @return array<string, mixed> */
    private function subscriptionEvent(): array
    {
        return [
            'id' => 'evt_created',
            'type' => 'customer.subscription.created',
            'data' => ['object' => [
                'id' => 'sub_pedix', 'customer' => 'cus_pedix', 'status' => 'trialing',
                'metadata' => ['type' => 'default'], 'trial_end' => now()->addDays(15)->timestamp,
                'items' => ['data' => [[
                    'id' => 'si_pedix', 'quantity' => 1,
                    'price' => ['id' => 'price_monthly', 'product' => 'prod_pedix'],
                ]]],
            ]],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function signedWebhook(array $payload, string $secret = 'whsec_example'): TestResponse
    {
        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$content, $secret);

        return $this->call('POST', route('cashier.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $content);
    }
}
