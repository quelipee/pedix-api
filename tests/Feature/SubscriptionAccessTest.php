<?php

namespace Tests\Feature;

use App\Contracts\SubscriptionGateway;
use App\Data\SubscriptionCheckout;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubscriptionAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->freezeTime();
        config(['billing.enforce_subscriptions' => true]);
    }

    public function test_operational_access_requires_a_current_paid_or_trial_period(): void
    {
        foreach ([
            [null, [], false],
            ['active', ['current_period_ends_at' => now()->addDay()], true],
            ['active', ['current_period_ends_at' => now()->subSecond()], false],
            ['active', [], false],
            ['trialing', ['trial_ends_at' => now()->addDay()], true],
            ['trialing', ['trial_ends_at' => now()], false],
            ['trialing', ['trial_ends_at' => now()->subSecond()], false],
            ['past_due', ['current_period_ends_at' => now()->addDay()], false],
            ['unpaid', [], false],
            ['incomplete', [], false],
            ['incomplete_expired', [], false],
            ['paused', [], false],
            ['canceled', ['ends_at' => now()->subSecond()], false],
            ['active', ['current_period_ends_at' => now()->addDay(), 'ends_at' => now()->addHour()], true],
            ['active', ['current_period_ends_at' => now()->addDay(), 'ends_at' => now()], false],
            ['canceled', ['current_period_ends_at' => now()->addDay(), 'ends_at' => now()->addHour()], true],
        ] as [$status, $attributes, $allowed]) {
            $owner = User::factory()->create();
            if ($status !== null) {
                $owner->currentTeam->subscriptions()->create([
                    'type' => 'default', 'stripe_id' => 'sub_'.$owner->id, 'stripe_status' => $status, ...$attributes,
                ]);
            }

            $response = $this->actingAs($owner)->getJson(route('orders.index'));
            $response->assertStatus($allowed ? 200 : 402);
            $page = $this->get(route('dashboard', $owner->currentTeam->slug));
            if ($allowed) {
                $page->assertOk();
            } else {
                $page->assertRedirect(route('billing.required', $owner->currentTeam->slug));
            }

            $this->get(route('billing.index', $owner->currentTeam->slug))->assertOk();
        }
    }

    public function test_members_can_see_recovery_instructions_but_cannot_manage_billing(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($owner->currentTeam);

        $this->actingAs($member)->get(route('dashboard', $owner->currentTeam->slug))
            ->assertRedirect(route('billing.required', $owner->currentTeam->slug));
        $this->get(route('billing.required', $owner->currentTeam->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('billing/Required')->where('permissions.canManageBilling', false));
        $this->get(route('billing.index', $owner->currentTeam->slug))->assertForbidden();
    }

    public function test_an_active_subscription_in_another_team_does_not_bypass_the_gate(): void
    {
        $owner = User::factory()->create();
        $owner->currentTeam->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_active', 'stripe_status' => 'active', 'current_period_ends_at' => now()->addDay(),
        ]);
        $other = User::factory()->create();
        $other->currentTeam->members()->attach($owner, ['role' => TeamRole::Member->value]);

        $this->actingAs($owner)->get(route('dashboard', $other->currentTeam->slug))
            ->assertRedirect(route('billing.required', $other->currentTeam->slug));
        $this->getJson(route('orders.index'))->assertStatus(402);
    }

    public function test_production_cannot_disable_subscription_enforcement(): void
    {
        config(['billing.enforce_subscriptions' => false]);
        $owner = User::factory()->create();
        $this->app->instance('env', 'production');
        try {
            $this->actingAs($owner)->getJson(route('orders.index'))->assertStatus(402);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_expired_owner_can_open_portal_and_start_a_paid_renewal_without_new_trial(): void
    {
        config([
            'cashier.key' => 'pk_test_access', 'cashier.secret' => 'sk_test_access',
            'cashier.webhook.secret' => 'whsec_access', 'billing.plans.monthly.price_id' => 'price_monthly',
        ]);
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_access'])->save();
        $team->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_old_access', 'stripe_status' => 'canceled', 'ends_at' => now()->subDay(),
        ]);
        $mock = $this->mock(SubscriptionGateway::class);
        $mock->shouldReceive('billingPortal')->once()->andReturn('https://billing.stripe.com/access');
        $mock->shouldReceive('createCheckout')->once()->withArgs(fn ($team, $plan, int $days): bool => $days === 0)
            ->andReturn(new SubscriptionCheckout('cs_access', 'https://checkout.stripe.com/access', 'open'));

        $this->actingAs($owner)->postJson(route('cash-registers.open'), ['opening_balance' => '0.00'])->assertStatus(402);
        $this->post(route('billing.portal', $team->slug))->assertRedirect('https://billing.stripe.com/access');
        $this->post(route('billing.checkout', $team->slug), ['plan' => 'monthly'])->assertRedirect('https://checkout.stripe.com/access');
    }
}
