<?php

namespace Tests\Feature;

use App\Enums\BillingPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\StripeSubscriptionGateway;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    protected function setUp(): void
    {
        parent::setUp();
        config(['billing.plans.monthly.price_id' => 'price_monthly']);
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

        $checkout = (new StripeSubscriptionGateway(new StripeClient('sk_test_example')))
            ->createCheckout($team, BillingPlan::Monthly, 15);

        $this->assertSame('cs_pedix', $checkout->id);
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
