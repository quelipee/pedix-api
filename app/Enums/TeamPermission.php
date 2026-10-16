<?php

namespace App\Enums;

enum TeamPermission: string
{
    case ViewDashboard = 'dashboard:view';

    case ViewOrders = 'orders:view';
    case CreateOrder = 'orders:create';
    case UpdateOrderStatus = 'orders:update-status';
    case DispatchOrder = 'orders:dispatch';
    case CancelOrder = 'orders:cancel';

    case ViewProducts = 'products:view';
    case ManageProducts = 'products:manage';

    case ViewCustomers = 'customers:view';
    case ManageCustomers = 'customers:manage';
    case ManageCouriers = 'couriers:manage';

    case ViewCashRegister = 'cash-register:view';
    case ManageCashRegister = 'cash-register:manage';
    case ViewReports = 'reports:view';
    case ManageBilling = 'billing:manage';

    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';
}
