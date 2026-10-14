<?php

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
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
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
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('orders', KitchenPanelController::class)->name('orders.manage');
        Route::get('orders/history', OrderManagementController::class)->name('orders.history');
        Route::get('orders/create', CreateOrderPageController::class)->name('orders.create');
        Route::get('products', ProductCatalogController::class)->name('products.catalog');
        Route::get('products/create', CreateProductPageController::class)->name('products.create');
        Route::get('products/{product}/edit', EditProductPageController::class)->name('products.edit');
        Route::get('cash-register', CashRegisterPageController::class)->name('cash-registers.index');
        Route::get('customers', CustomerListController::class)->name('customers.index');
        Route::get('customers/create', CreateCustomerPageController::class)->name('customers.create');
        Route::get('customers/{customer}', CustomerDetailsController::class)->name('customers.show');
    });

Route::middleware(['auth'])->group(function () {
    Route::prefix('api/v1')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::patch('orders/{order}/advance', [OrderController::class, 'advance'])->name('orders.advance');
        Route::patch('orders/{order}/dispatch', [OrderController::class, 'dispatch'])->name('orders.dispatch');
        Route::post('couriers', [CourierController::class, 'store'])->name('couriers.store');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::patch('couriers/{courier}/availability', [CourierController::class, 'updateAvailability'])->name('couriers.availability');
        Route::post('cash-registers/open', [CashRegisterController::class, 'open'])->name('cash-registers.open');
        Route::post('cash-registers/{cashRegister}/movements', [CashRegisterController::class, 'movement'])->name('cash-registers.movements.store');
        Route::patch('cash-registers/{cashRegister}/close', [CashRegisterController::class, 'close'])->name('cash-registers.close');
    });
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
