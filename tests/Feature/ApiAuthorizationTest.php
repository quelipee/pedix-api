<?php

namespace Tests\Feature;

use App\Enums\TeamPermission;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\EnsureTeamPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_business_api_routes_share_the_security_middleware(): void
    {
        $routePermissions = [
            'products.index' => TeamPermission::ViewProducts,
            'products.store' => TeamPermission::ManageProducts,
            'products.update' => TeamPermission::ManageProducts,
            'products.destroy' => TeamPermission::ManageProducts,
            'orders.index' => TeamPermission::ViewOrders,
            'orders.store' => TeamPermission::CreateOrder,
            'orders.advance' => TeamPermission::UpdateOrderStatus,
            'orders.dispatch' => TeamPermission::DispatchOrder,
            'orders.cancel' => TeamPermission::CancelOrder,
            'couriers.store' => TeamPermission::ManageCouriers,
            'couriers.availability' => TeamPermission::ManageCouriers,
            'customers.store' => TeamPermission::ManageCustomers,
            'cash-registers.open' => TeamPermission::ManageCashRegister,
            'cash-registers.movements.store' => TeamPermission::ManageCashRegister,
            'cash-registers.close' => TeamPermission::ManageCashRegister,
        ];

        foreach ($routePermissions as $routeName => $permission) {
            $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

            $this->assertContains('auth', $middleware, "A rota {$routeName} deve exigir autenticação.");
            $this->assertContains('verified', $middleware, "A rota {$routeName} deve exigir e-mail confirmado.");
            $this->assertContains(EnsureTeamMembership::class, $middleware, "A rota {$routeName} deve validar a equipe atual.");
            $this->assertContains(EnsureTeamPermission::class.':'.$permission->value, $middleware, "A rota {$routeName} deve validar a permissão da função.");
            $this->assertContains('throttle:120,1', $middleware, "A rota {$routeName} deve limitar requisições.");
        }
    }

    public function test_unverified_users_cannot_access_the_business_api(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->getJson(route('products.index'))
            ->assertForbidden();
    }

    public function test_users_without_a_current_team_cannot_access_the_business_api(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['current_team_id' => null])->save();

        $this->actingAs($user)
            ->getJson(route('orders.index'))
            ->assertForbidden();
    }

    public function test_users_removed_from_the_current_team_cannot_access_the_business_api(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->members()->detach($user);

        $this->actingAs($user->fresh())
            ->getJson(route('orders.index'))
            ->assertForbidden();
    }
}
