<?php

namespace Tests\Feature;

use App\Contracts\SubscriptionGateway;
use App\Enums\BillingPlan;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Models\Team;
use App\Models\User;
use App\Services\StripeSubscriptionGateway;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Mockery;
use Mockery\MockInterface;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeSubscriptionGatewayTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $http;

    public function test_route_inspection_can_resolve_stripe_dependencies_without_a_secret(): void
    {
        config(['cashier.secret' => '']);
        $this->app->forgetInstance(StripeClient::class);

        $client = $this->app->make(StripeClient::class);
        $this->assertNull($client->getApiKey());
        $controller = app('router')->getRoutes()->getByName('cashier.webhook')->getController();
        $this->assertInstanceOf(StripeWebhookController::class, $controller);

        $owner = User::factory()->create();
        foreach (['billing.checkout', 'billing.portal'] as $route) {
            $this->actingAs($owner)->postJson(route($route, $owner->currentTeam->slug), ['plan' => 'monthly'])
                ->assertStatus(503);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'billing.plans.monthly.price_id' => 'price_monthly',
            'cashier.secret' => 'sk_test_example',
        ]);
        $this->http = Mockery::mock(ClientInterface::class);
        ApiRequestor::setHttpClient($this->http);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());
        parent::tearDown();
    }

    public function test_checkout_uses_the_configured_price_team_customer_and_trial(): void
    {
        $team = $this->preparedTeam();
        $this->priceResponse();
        $this->httpResponse('post', '/v1/checkout/sessions', [
            'id' => 'cs_pedix', 'object' => 'checkout.session', 'status' => 'open', 'url' => 'https://checkout.stripe.com/pedix',
        ], function (array $params, array $headers) use ($team): void {
            $this->assertSame('subscription', $params['mode']);
            $this->assertSame('cus_pedix', $params['customer']);
            $this->assertSame('price_monthly', $params['line_items'][0]['price']);
            $this->assertSame(15, $params['subscription_data']['trial_period_days']);
            $this->assertSame((string) $team->id, $params['subscription_data']['metadata']['team_id']);
            $this->assertStringContainsString('/'.$team->slug.'/billing', $params['success_url']);
            $this->assertStringContainsString('pedix-checkout-attempt-example', implode("\n", $headers));
        });

        $checkout = $this->app->make(SubscriptionGateway::class)
            ->createCheckout($team, BillingPlan::Monthly, 15);

        $this->assertSame('cs_pedix', $checkout->id);
    }

    public function test_stripe_client_is_resolved_from_the_container_with_cashier_configuration(): void
    {
        $client = $this->app->make(StripeClient::class);

        $this->assertSame('sk_test_example', $client->getApiKey());
        $this->assertSame(Cashier::STRIPE_VERSION, $client->getStripeVersion());
        $this->assertSame(Cashier::$apiBaseUrl, $client->getApiBase());
        $this->assertSame($client, $this->app->make(StripeClient::class));
    }

    public function test_cashier_can_resolve_the_client_with_custom_configuration(): void
    {
        $this->app->make(StripeClient::class);

        $client = Cashier::stripe([
            'api_key' => 'sk_test_override',
            'api_base' => 'https://stripe.example.test',
        ]);

        $this->assertSame('sk_test_override', $client->getApiKey());
        $this->assertSame('https://stripe.example.test', $client->getApiBase());
        $this->assertSame(Cashier::STRIPE_VERSION, $client->getStripeVersion());
    }

    public function test_incorrect_amount_or_currency_is_rejected_before_creating_checkout(): void
    {
        $team = $this->preparedTeam();
        $this->priceResponse(['unit_amount' => 1, 'currency' => 'usd']);

        $this->expectException(ValidationException::class);

        (new StripeSubscriptionGateway(new StripeClient('sk_test_example')))
            ->createCheckout($team, BillingPlan::Monthly, 15);
    }

    public function test_new_customer_is_bound_to_the_team_and_uses_the_owners_email(): void
    {
        $team = $this->preparedTeam();
        $team->forceFill(['stripe_id' => null])->save();
        $this->priceResponse();
        $this->httpResponse('post', '/v1/customers', ['id' => 'cus_new', 'object' => 'customer'], function (array $params) use ($team): void {
            $this->assertSame($team->stripeEmail(), $params['email']);
            $this->assertSame((string) $team->id, $params['metadata']['team_id']);
        });
        $this->httpResponse('post', '/v1/checkout/sessions', [
            'id' => 'cs_new', 'object' => 'checkout.session', 'status' => 'open', 'url' => 'https://checkout.stripe.com/new',
        ], function (array $params): void {
            $this->assertSame('cus_new', $params['customer']);
            $this->assertArrayNotHasKey('trial_period_days', $params['subscription_data']);
        });

        (new StripeSubscriptionGateway(new StripeClient('sk_test_example')))
            ->createCheckout($team, BillingPlan::Monthly, 0);

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'stripe_id' => 'cus_new']);
    }

    private function preparedTeam(): Team
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $team->forceFill([
            'stripe_id' => 'cus_pedix',
            'billing_checkout_token' => 'attempt-example',
            'billing_checkout_expires_at' => now()->addHour(),
        ])->save();

        return $team;
    }

    public function test_subscription_snapshot_is_retrieved_from_stripe_by_id(): void
    {
        $this->httpResponse('get', '/v1/subscriptions/sub_snapshot', [
            'id' => 'sub_snapshot', 'object' => 'subscription', 'status' => 'canceled', 'customer' => 'cus_snapshot',
        ]);
        $snapshot = $this->app->make(SubscriptionGateway::class)->subscriptionSnapshot('sub_snapshot');
        $this->assertSame('sub_snapshot', $snapshot['id']);
        $this->assertSame('canceled', $snapshot['status']);
        $this->assertSame('cus_snapshot', $snapshot['customer']);
    }

    /** @param array<string, mixed> $overrides */
    private function priceResponse(array $overrides = []): void
    {
        $this->httpResponse('get', '/v1/prices/price_monthly', [
            'id' => 'price_monthly', 'object' => 'price', 'active' => true,
            'currency' => 'brl', 'unit_amount' => 7990,
            'recurring' => ['interval' => 'month', 'interval_count' => 1],
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  Closure(array<string, mixed>, array<string>): void|null  $inspect
     */
    private function httpResponse(string $method, string $path, array $response, ?Closure $inspect = null): void
    {
        $this->http->shouldReceive('request')->once()->withArgs(function (...$args) use ($method, $path, $inspect): bool {
            if ($args[0] !== $method || ! str_ends_with($args[1], $path)) {
                return false;
            }

            if ($inspect !== null) {
                $inspect($args[3], $args[2]);
            }

            return true;
        })->andReturn([json_encode($response, JSON_THROW_ON_ERROR), 200, []]);
    }
}
