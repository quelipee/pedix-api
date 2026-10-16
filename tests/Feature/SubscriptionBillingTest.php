<?php

namespace Tests\Feature;

use App\Contracts\SubscriptionGateway;
use App\Data\SubscriptionCheckout;
use App\Enums\BillingPlan;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cashier.key' => 'pk_test_example',
            'cashier.secret' => 'sk_test_example',
            'cashier.webhook.secret' => 'whsec_example',
            'billing.plans.monthly.price_id' => 'price_monthly',
            'billing.plans.yearly.price_id' => 'price_yearly',
            'billing.trial_days' => 15,
        ]);
    }

    public function test_owner_can_view_the_team_plans_without_exposing_stripe_credentials(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('billing/Index')
                ->where('billing.configured', true)
                ->where('billing.trialDays', 15)
                ->where('billing.plans.0.amount', 7990)
                ->where('billing.plans.1.amount', 79900)
                ->where('permissions.canManageBilling', true)
                ->missing('billing.secret'));
    }

    public function test_billing_can_be_opened_before_stripe_is_configured_but_checkout_is_disabled(): void
    {
        config(['cashier.secret' => null]);
        $owner = User::factory()->create();

        $this->actingAs($owner)->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('billing.configured', false)
                ->where('billing.plans.0.available', false));

        $this->actingAs($owner)->postJson(route('billing.checkout'), ['plan' => 'monthly'])
            ->assertStatus(503);
    }

    public function test_only_the_team_owner_can_view_or_manage_billing(): void
    {
        foreach ([TeamRole::Member, TeamRole::Admin] as $role) {
            $user = User::factory()->create();
            $team = Team::factory()->create();
            $team->members()->attach($user, ['role' => $role->value]);
            $user->switchTeam($team);

            $this->actingAs($user)->get(route('billing.index'))->assertForbidden();
            $this->actingAs($user)->postJson(route('billing.checkout'), ['plan' => 'monthly'])->assertForbidden();
            $this->actingAs($user)->postJson(route('billing.portal'))->assertForbidden();
        }
    }

    public function test_owner_cannot_access_billing_for_a_team_they_do_not_belong_to(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->postJson(route('billing.checkout', ['current_team' => $other->currentTeam->slug]), [
            'plan' => 'monthly',
        ])->assertForbidden();
    }

    public function test_first_checkout_uses_the_server_plan_and_the_15_day_trial(): void
    {
        $owner = User::factory()->create();
        $teamId = $owner->current_team_id;
        $this->mock(SubscriptionGateway::class, function (MockInterface $mock) use ($teamId): void {
            $mock->shouldReceive('createCheckout')->once()
                ->withArgs(fn (Team $team, BillingPlan $plan, int $days): bool => $team->id === $teamId
                    && $plan === BillingPlan::Monthly && $days === 15 && $team->billing_checkout_token !== null)
                ->andReturn(new SubscriptionCheckout('cs_test', 'https://checkout.stripe.com/test', 'open'));
        });

        $this->actingAs($owner)->post(route('billing.checkout'), [
            'plan' => 'monthly', 'price_id' => 'price_attacker', 'amount' => 1, 'trial_days' => 999,
        ])->assertRedirect('https://checkout.stripe.com/test');

        $this->assertDatabaseHas('teams', ['id' => $teamId, 'billing_checkout_id' => 'cs_test', 'billing_checkout_plan' => 'monthly']);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_unknown_or_unconfigured_plan_cannot_start_checkout(): void
    {
        $owner = User::factory()->create();
        $this->mock(SubscriptionGateway::class)->shouldNotReceive('createCheckout');

        $this->actingAs($owner)->postJson(route('billing.checkout'), ['plan' => 'free'])
            ->assertUnprocessable()->assertJsonValidationErrors('plan');

        config(['billing.plans.yearly.price_id' => null]);
        $this->actingAs($owner)->postJson(route('billing.checkout'), ['plan' => 'yearly'])
            ->assertUnprocessable()->assertJsonValidationErrors('plan');
    }

    public function test_an_existing_or_pending_subscription_cannot_be_duplicated(): void
    {
        $owner = User::factory()->create();
        $this->mock(SubscriptionGateway::class)->shouldNotReceive('createCheckout');

        foreach (['active', 'trialing', 'past_due', 'incomplete', 'unpaid', 'paused'] as $status) {
            $subscription = $owner->currentTeam->subscriptions()->create([
                'type' => 'default', 'stripe_id' => 'sub_'.$status, 'stripe_status' => $status, 'stripe_price' => 'price_monthly',
            ]);

            $this->actingAs($owner)->postJson(route('billing.checkout'), ['plan' => 'monthly'])
                ->assertUnprocessable()->assertJsonValidationErrors('billing');

            $subscription->delete();
        }
    }

    public function test_open_checkout_is_reused_instead_of_creating_another_session(): void
    {
        $owner = User::factory()->create();
        $owner->currentTeam->forceFill(['billing_checkout_id' => 'cs_open', 'billing_checkout_plan' => 'monthly'])->save();

        $this->mock(SubscriptionGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findCheckout')->once()->with('cs_open')
                ->andReturn(new SubscriptionCheckout('cs_open', 'https://checkout.stripe.com/existing', 'open'));
            $mock->shouldNotReceive('createCheckout');
        });

        $this->actingAs($owner)->post(route('billing.checkout'), ['plan' => 'monthly'])
            ->assertRedirect('https://checkout.stripe.com/existing');
    }

    public function test_switching_the_plan_expires_the_previous_checkout_before_creating_another(): void
    {
        $owner = User::factory()->create();
        $owner->currentTeam->forceFill(['billing_checkout_id' => 'cs_old', 'billing_checkout_plan' => 'monthly'])->save();

        $this->mock(SubscriptionGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findCheckout')->once()->with('cs_old')
                ->andReturn(new SubscriptionCheckout('cs_old', 'https://checkout.stripe.com/old', 'open'));
            $mock->shouldReceive('expireCheckout')->once()->with('cs_old')->ordered();
            $mock->shouldReceive('createCheckout')->once()->withArgs(fn (Team $team, BillingPlan $plan, int $days): bool => $plan === BillingPlan::Yearly && $days === 15)->ordered()
                ->andReturn(new SubscriptionCheckout('cs_yearly', 'https://checkout.stripe.com/yearly', 'open'));
        });

        $this->actingAs($owner)->post(route('billing.checkout'), ['plan' => 'yearly'])
            ->assertRedirect('https://checkout.stripe.com/yearly');
    }

    public function test_completed_checkout_waits_for_the_webhook_before_allowing_another_subscription(): void
    {
        $owner = User::factory()->create();
        $owner->currentTeam->forceFill(['billing_checkout_id' => 'cs_complete'])->save();

        $this->mock(SubscriptionGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findCheckout')->once()->andReturn(new SubscriptionCheckout('cs_complete', null, 'complete'));
            $mock->shouldNotReceive('createCheckout');
        });

        $this->actingAs($owner)->postJson(route('billing.checkout'), ['plan' => 'monthly'])
            ->assertUnprocessable()->assertJsonValidationErrors('billing');
    }

    public function test_trial_is_not_given_again_after_a_previous_subscription(): void
    {
        $owner = User::factory()->create();
        $owner->currentTeam->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_old', 'stripe_status' => 'canceled', 'ends_at' => now()->subDay(),
        ]);

        $this->mock(SubscriptionGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createCheckout')->once()
                ->withArgs(fn (Team $team, BillingPlan $plan, int $days): bool => $days === 0)
                ->andReturn(new SubscriptionCheckout('cs_new', 'https://checkout.stripe.com/new', 'open'));
        });

        $this->actingAs($owner)->post(route('billing.checkout'), ['plan' => 'yearly'])->assertRedirect();
    }

    public function test_portal_uses_only_the_current_team_stripe_customer(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_own'])->save();

        $this->mock(SubscriptionGateway::class, function (MockInterface $mock) use ($team): void {
            $mock->shouldReceive('billingPortal')->once()
                ->withArgs(fn (Team $value): bool => $value->id === $team->id && $value->stripe_id === 'cus_own')
                ->andReturn('https://billing.stripe.com/own');
        });

        $this->actingAs($owner)->post(route('billing.portal'), ['customer' => 'cus_other'])
            ->assertRedirect('https://billing.stripe.com/own');
    }

    public function test_success_url_alone_does_not_activate_a_subscription(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->get(route('billing.index', ['checkout' => 'success']))
            ->assertInertia(fn (Assert $page) => $page->where('billing.hasSubscription', false));

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_team_with_a_running_subscription_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_running', 'stripe_status' => 'active',
        ]);

        $this->actingAs($owner)->deleteJson(route('teams.destroy', $team), ['name' => $team->name])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertNotSoftDeleted($team);
    }

    public function test_owner_cannot_delete_their_account_while_a_team_has_a_subscription(): void
    {
        $owner = User::factory()->create();
        $owner->currentTeam->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_running', 'stripe_status' => 'active',
        ]);

        $this->actingAs($owner)->deleteJson(route('profile.destroy'), ['password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertModelExists($owner);
    }

    public function test_a_subscription_scheduled_to_end_still_blocks_duplicate_checkout(): void
    {
        $owner = User::factory()->create();
        $owner->currentTeam->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_grace', 'stripe_status' => 'canceled', 'ends_at' => now()->addWeek(),
        ]);

        $this->mock(SubscriptionGateway::class)->shouldNotReceive('createCheckout');
        $this->actingAs($owner)->postJson(route('billing.checkout'), ['plan' => 'monthly'])
            ->assertUnprocessable()->assertJsonValidationErrors('billing');
    }
}
