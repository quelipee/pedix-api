<?php

namespace App\Providers;

use App\Contracts\CashRegisterCalculator;
use App\Contracts\OrderCommandFormatter;
use App\Contracts\SalesReport;
use App\Contracts\SubscriptionGateway;
use App\Models\Team;
use App\Services\DefaultOrderCommandFormatter;
use App\Services\EloquentCashRegisterCalculator;
use App\Services\EloquentSalesReport;
use App\Services\StripeSubscriptionGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CashRegisterCalculator::class, EloquentCashRegisterCalculator::class);
        $this->app->bind(OrderCommandFormatter::class, DefaultOrderCommandFormatter::class);
        $this->app->bind(SalesReport::class, EloquentSalesReport::class);
        $this->app->bind(SubscriptionGateway::class, StripeSubscriptionGateway::class);
        $this->app->singleton(StripeClient::class, fn (): StripeClient => Cashier::stripe());

        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Cashier::useCustomerModel(Team::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
