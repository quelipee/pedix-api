<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamBusinessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_access_daily_operation_pages(): void
    {
        [$member, $team] = $this->userWithRole(TeamRole::Member);

        foreach (['dashboard', 'orders.manage', 'orders.create', 'customers.index'] as $routeName) {
            $this->actingAs($member)
                ->get(route($routeName, $team))
                ->assertOk();
        }
    }

    public function test_member_cannot_access_management_pages(): void
    {
        [$member, $team] = $this->userWithRole(TeamRole::Member);

        foreach (['products.catalog', 'products.create', 'cash-registers.index', 'reports.index'] as $routeName) {
            $this->actingAs($member)
                ->get(route($routeName, $team))
                ->assertForbidden();
        }
    }

    public function test_member_cannot_call_restricted_business_actions(): void
    {
        [$member] = $this->userWithRole(TeamRole::Member);

        $this->actingAs($member)->postJson(route('products.store'), [])->assertForbidden();
        $this->actingAs($member)->postJson(route('couriers.store'), [])->assertForbidden();
        $this->actingAs($member)->postJson(route('cash-registers.open'), [])->assertForbidden();
    }

    public function test_admin_can_access_management_pages_and_actions(): void
    {
        [$admin, $team] = $this->userWithRole(TeamRole::Admin);

        foreach (['products.catalog', 'products.create', 'cash-registers.index', 'reports.index'] as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName, $team))
                ->assertOk();
        }

        $this->actingAs($admin)
            ->postJson(route('products.store'), [])
            ->assertUnprocessable();
    }

    public function test_inertia_shares_permissions_for_the_current_role(): void
    {
        [$member, $team] = $this->userWithRole(TeamRole::Member);

        $this->actingAs($member)
            ->get(route('dashboard', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.canCreateOrder', true)
                ->where('permissions.canCancelOrder', false)
                ->where('permissions.canManageProducts', false)
                ->where('permissions.canViewReports', false));
    }

    /**
     * @return array{User, Team}
     */
    private function userWithRole(TeamRole $role): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => $role->value]);
        $user->switchTeam($team);

        return [$user->fresh(), $team];
    }
}
