<?php

namespace Tests\Unit;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TeamRolePermissionTest extends TestCase
{
    public function test_owner_has_every_team_permission(): void
    {
        foreach (TeamPermission::cases() as $permission) {
            $this->assertTrue(TeamRole::Owner->hasPermission($permission));
        }
    }

    public function test_admin_can_manage_the_business_without_managing_members_or_deleting_the_team(): void
    {
        $this->assertTrue(TeamRole::Admin->hasPermission(TeamPermission::ManageProducts));
        $this->assertTrue(TeamRole::Admin->hasPermission(TeamPermission::CancelOrder));
        $this->assertTrue(TeamRole::Admin->hasPermission(TeamPermission::ManageCashRegister));
        $this->assertTrue(TeamRole::Admin->hasPermission(TeamPermission::ViewReports));

        $this->assertFalse(TeamRole::Admin->hasPermission(TeamPermission::DeleteTeam));
        $this->assertFalse(TeamRole::Admin->hasPermission(TeamPermission::AddMember));
        $this->assertFalse(TeamRole::Admin->hasPermission(TeamPermission::UpdateMember));
        $this->assertFalse(TeamRole::Admin->hasPermission(TeamPermission::RemoveMember));
    }

    #[DataProvider('memberPermissionProvider')]
    public function test_member_has_only_daily_operation_permissions(TeamPermission $permission, bool $allowed): void
    {
        $this->assertSame($allowed, TeamRole::Member->hasPermission($permission));
    }

    /**
     * @return array<string, array{TeamPermission, bool}>
     */
    public static function memberPermissionProvider(): array
    {
        return [
            'view dashboard' => [TeamPermission::ViewDashboard, true],
            'view orders' => [TeamPermission::ViewOrders, true],
            'create order' => [TeamPermission::CreateOrder, true],
            'advance order' => [TeamPermission::UpdateOrderStatus, true],
            'dispatch order' => [TeamPermission::DispatchOrder, true],
            'serve customers' => [TeamPermission::ManageCustomers, true],
            'cancel order' => [TeamPermission::CancelOrder, false],
            'manage products' => [TeamPermission::ManageProducts, false],
            'manage couriers' => [TeamPermission::ManageCouriers, false],
            'manage cash register' => [TeamPermission::ManageCashRegister, false],
            'view reports' => [TeamPermission::ViewReports, false],
        ];
    }
}
