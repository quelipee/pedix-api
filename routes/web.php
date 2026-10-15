<?php

use App\Enums\TeamPermission;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\CashRegisters\CashRegisterController;
use App\Http\Controllers\CashRegisters\CashRegisterPageController;
use App\Http\Controllers\Couriers\CourierController;
use App\Http\Controllers\Customers\CreateCustomerPageController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\CustomerDetailsController;
use App\Http\Controllers\Customers\CustomerListController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Orders\CreateOrderPageController;
use App\Http\Controllers\Orders\KitchenPanelController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Orders\OrderManagementController;
use App\Http\Controllers\Products\CreateProductPageController;
use App\Http\Controllers\Products\EditProductPageController;
use App\Http\Controllers\Products\ProductCatalogController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\EnsureTeamPermission;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('google.callback');

Route::prefix('{current_team}')
    ->middleware(array_filter([
        'auth',
        config('auth.require_email_verification') ? 'verified' : null,
        EnsureTeamMembership::class,
    ]))
    ->group(function () {
        Route::get('dashboard', DashboardController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::ViewDashboard->value)
            ->name('dashboard');
        Route::get('orders', KitchenPanelController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::ViewOrders->value)
            ->name('orders.manage');
        Route::get('orders/history', OrderManagementController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::ViewOrders->value)
            ->name('orders.history');
        Route::get('orders/create', CreateOrderPageController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::CreateOrder->value)
            ->name('orders.create');
        Route::get('products', ProductCatalogController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::ViewProducts->value)
            ->name('products.catalog');
        Route::get('products/create', CreateProductPageController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageProducts->value)
            ->name('products.create');
        Route::get('products/{product}/edit', EditProductPageController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageProducts->value)
            ->name('products.edit');
        Route::get('cash-register', CashRegisterPageController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::ViewCashRegister->value)
            ->name('cash-registers.index');
        Route::get('customers', CustomerListController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::ViewCustomers->value)
            ->name('customers.index');
        Route::get('customers/create', CreateCustomerPageController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageCustomers->value)
            ->name('customers.create');
        Route::get('customers/{customer}', CustomerDetailsController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::ViewCustomers->value)
            ->name('customers.show');
        Route::get('reports', ReportController::class)
            ->middleware(EnsureTeamPermission::class.':'.TeamPermission::ViewReports->value)
            ->name('reports.index');
    });

Route::prefix('api/v1')
    ->middleware(array_filter([
        'auth',
        config('auth.require_email_verification') ? 'verified' : null,
        EnsureTeamMembership::class,
        'throttle:120,1',
    ]))
    ->group(function () {
        Route::get('products', [ProductController::class, 'index'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::ViewProducts->value)->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageProducts->value)->name('products.store');
        Route::patch('products/{product}', [ProductController::class, 'update'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageProducts->value)->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageProducts->value)->name('products.destroy');
        Route::get('orders', [OrderController::class, 'index'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::ViewOrders->value)->name('orders.index');
        Route::post('orders', [OrderController::class, 'store'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::CreateOrder->value)->name('orders.store');
        Route::patch('orders/{order}/advance', [OrderController::class, 'advance'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::UpdateOrderStatus->value)->name('orders.advance');
        Route::patch('orders/{order}/dispatch', [OrderController::class, 'dispatch'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::DispatchOrder->value)->name('orders.dispatch');
        Route::patch('orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::CancelOrder->value)->name('orders.cancel');
        Route::post('couriers', [CourierController::class, 'store'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageCouriers->value)->name('couriers.store');
        Route::post('customers', [CustomerController::class, 'store'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageCustomers->value)->name('customers.store');
        Route::patch('couriers/{courier}/availability', [CourierController::class, 'updateAvailability'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageCouriers->value)->name('couriers.availability');
        Route::post('cash-registers/open', [CashRegisterController::class, 'open'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageCashRegister->value)->name('cash-registers.open');
        Route::post('cash-registers/{cashRegister}/movements', [CashRegisterController::class, 'movement'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageCashRegister->value)->name('cash-registers.movements.store');
        Route::patch('cash-registers/{cashRegister}/close', [CashRegisterController::class, 'close'])->middleware(EnsureTeamPermission::class.':'.TeamPermission::ManageCashRegister->value)->name('cash-registers.close');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
