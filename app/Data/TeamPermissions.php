<?php

namespace App\Data;

readonly class TeamPermissions
{
    public function __construct(
        public bool $canViewDashboard,
        public bool $canViewOrders,
        public bool $canCreateOrder,
        public bool $canUpdateOrderStatus,
        public bool $canDispatchOrder,
        public bool $canCancelOrder,
        public bool $canViewProducts,
        public bool $canManageProducts,
        public bool $canViewCustomers,
        public bool $canManageCustomers,
        public bool $canManageCouriers,
        public bool $canViewCashRegister,
        public bool $canManageCashRegister,
        public bool $canViewReports,
        public bool $canManageBilling,
        public bool $canUpdateTeam,
        public bool $canDeleteTeam,
        public bool $canAddMember,
        public bool $canUpdateMember,
        public bool $canRemoveMember,
        public bool $canCreateInvitation,
        public bool $canCancelInvitation,
    ) {
        //
    }
}
