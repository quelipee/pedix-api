<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_start_closed_and_share_the_status_and_permission(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();

        $this->assertFalse($owner->currentTeam->refresh()->is_open);
        $this->actingAs($owner)->get(route('dashboard', $owner->currentTeam->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('currentTeam.isOpen', false)
            ->where('permissions.canManageStoreStatus', true));
    }

    public function test_owner_and_admin_can_explicitly_open_and_close_only_their_store(): void
    {
        foreach ([TeamRole::Owner, TeamRole::Admin] as $role) {
            $user = User::factory()->create();
            $team = $user->currentTeam;
            $team->members()->updateExistingPivot($user->id, ['role' => $role->value]);
            $otherTeam = Team::factory()->create();

            foreach ([true, true, false, false] as $isOpen) {
                $this->actingAs($user)->from(route('dashboard', $team->slug))
                    ->patch(route('store-status.update', $team->slug), ['is_open' => $isOpen, 'team_id' => $otherTeam->id])
                    ->assertRedirect(route('dashboard', $team->slug));
                $this->assertSame($isOpen, $team->refresh()->is_open);
                $this->assertFalse($otherTeam->refresh()->is_open);
            }
        }
    }

    public function test_member_cannot_change_status_but_can_see_it(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member]);
        $member->switchTeam($team);

        $this->actingAs($member)->patchJson(route('store-status.update', $team->slug), ['is_open' => true])->assertForbidden();
        $this->assertFalse($team->refresh()->is_open);
        $this->get(route('dashboard', $team->slug))->assertInertia(fn (Assert $page) => $page
            ->where('currentTeam.isOpen', false)->where('permissions.canManageStoreStatus', false));
    }

    public function test_guests_and_other_teams_cannot_change_status(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $this->patchJson(route('store-status.update', $team->slug), ['is_open' => true])->assertUnauthorized();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->patchJson(route('store-status.update', $team->slug), ['is_open' => true])->assertForbidden();
        $this->assertFalse($team->refresh()->is_open);
    }

    public function test_status_must_be_a_boolean_and_requires_an_active_subscription(): void
    {
        $owner = User::factory()->create();
        $url = route('store-status.update', $owner->currentTeam->slug);
        foreach ([[], ['is_open' => null], ['is_open' => 'open'], ['is_open' => 2]] as $payload) {
            $this->actingAs($owner)->patchJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('is_open');
        }
        config(['billing.enforce_subscriptions' => true]);
        $this->patchJson($url, ['is_open' => true])->assertStatus(402);
        $this->assertFalse($owner->currentTeam->refresh()->is_open);
    }

    public function test_online_order_eligibility_requires_both_open_store_and_valid_subscription(): void
    {
        $team = User::factory()->create()->currentTeam;
        $team->forceFill(['is_open' => true])->save();
        $this->assertFalse($team->canAcceptOnlineOrders());
        $team->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_store_status',
            'stripe_status' => 'trialing', 'trial_ends_at' => now()->addDay(),
        ]);
        $team->unsetRelation('subscriptions');
        $this->assertTrue($team->canAcceptOnlineOrders());
        $team->forceFill(['is_open' => false])->save();
        $this->assertFalse($team->canAcceptOnlineOrders());
    }
}
