<?php

namespace App\Providers;
use App\Repository\Interface\EmployeeRepositoryInterface;
use App\Repository\EmployeeRepository;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Repository\Interface\LeaveRepositoryInterface;
use App\Repository\LeaveRepository;
use App\Repository\Interface\AdvanceSalaryRepositoryInterface;
use App\Repository\AdvanceSalaryRepository;
use App\Repository\Interface\LeaveAdjustmentRepositoryInterface;
use App\Repository\Interface\TravelRepositoryInterface;
use App\Repository\LeaveAdjustmentRepository;
use App\Repository\Interface\OvertimeRepositoryInterface;
use App\Repository\OvertimeRepository;
use App\Repository\TravelRepository;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EmployeeRepositoryInterface::class, EmployeeRepository::class);
        $this->app->singleton(LeaveRepositoryInterface::class, LeaveRepository::class);
        $this->app->singleton(LeaveAdjustmentRepositoryInterface::class, LeaveAdjustmentRepository::class);
        $this->app->singleton(AdvanceSalaryRepositoryInterface::class, AdvanceSalaryRepository::class);
        $this->app->singleton(OvertimeRepositoryInterface::class, OvertimeRepository::class);
        $this->app->singleton(TravelRepositoryInterface::class, TravelRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        
        // Configure Passport token expiration
        if (class_exists('Laravel\Passport\Passport')) {
            Passport::tokensExpireIn(now()->addMinutes(config('passport.token_expiration', 1440)));
            Passport::refreshTokensExpireIn(now()->addMinutes(config('passport.refresh_token_expiration', 20160)));
        }
    }
}
