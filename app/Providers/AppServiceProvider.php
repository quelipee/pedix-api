<?php

namespace App\Providers;

use App\Contracts\CashRegisterCalculator;
use App\Contracts\OrderCommandFormatter;
use App\Contracts\SalesReport;
use App\Services\DefaultOrderCommandFormatter;
use App\Services\EloquentCashRegisterCalculator;
use App\Services\EloquentSalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
