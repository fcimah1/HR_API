<?php

namespace App\Providers;

use App\Repository\Interface\EmployeeRepositoryInterface;
use App\Repository\EmployeeRepository;
use App\Repository\Interface\UserRepositoryInterface;
use App\Repository\UserRepository;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Repository\Interface\AttendanceRepositoryInterface;
use App\Repository\AttendanceRepository;
use App\Repository\Interface\LeaveRepositoryInterface;
use App\Repository\LeaveRepository;
use App\Repository\Interface\AdvanceSalaryRepositoryInterface;
use App\Repository\AdvanceSalaryRepository;
use App\Repository\Interface\LeaveAdjustmentRepositoryInterface;
use App\Repository\Interface\TravelRepositoryInterface;
use App\Repository\Interface\TravelTypeRepositoryInterface;
use App\Repository\LeaveAdjustmentRepository;
use App\Repository\Interface\OvertimeRepositoryInterface;
use App\Repository\OvertimeRepository;
use App\Repository\TravelRepository;
use App\Repository\TravelTypeRepository;
use App\Repository\Interface\LeaveTypeRepositoryInterface;
use App\Repository\LeaveTypeRepository;
use App\Repository\Interface\NotificationSettingRepositoryInterface;
use App\Repository\Interface\NotificationStatusRepositoryInterface;
use App\Repository\Interface\NotificationApprovalRepositoryInterface;
use App\Repository\NotificationSettingRepository;
use App\Repository\NotificationStatusRepository;
use App\Repository\NotificationApprovalRepository;
use App\Repository\Interface\HolidayRepositoryInterface;
use App\Repository\HolidayRepository;
use App\Repository\Interface\HourlyLeaveRepositoryInterface;
use App\Repository\HourlyLeaveRepository;
use App\Repository\Interface\SuggestionRepositoryInterface;
use App\Repository\SuggestionRepository;
use App\Repository\Interface\ComplaintRepositoryInterface;
use App\Repository\ComplaintRepository;
use App\Repository\Interface\ResignationRepositoryInterface;
use App\Repository\ResignationRepository;
use App\Repository\Interface\TransferRepositoryInterface;
use App\Repository\TransferRepository;
use Laravel\Passport\Passport;
use Laravel\Telescope\TelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EmployeeRepositoryInterface::class, EmployeeRepository::class);
        $this->app->singleton(UserRepositoryInterface::class, UserRepository::class);
        $this->app->singleton(AttendanceRepositoryInterface::class, AttendanceRepository::class);
        $this->app->singleton(LeaveRepositoryInterface::class, LeaveRepository::class);
        $this->app->singleton(LeaveAdjustmentRepositoryInterface::class, LeaveAdjustmentRepository::class);
        $this->app->singleton(AdvanceSalaryRepositoryInterface::class, AdvanceSalaryRepository::class);
        $this->app->singleton(OvertimeRepositoryInterface::class, OvertimeRepository::class);
        $this->app->singleton(TravelRepositoryInterface::class, TravelRepository::class);
        $this->app->bind(TravelTypeRepositoryInterface::class, TravelTypeRepository::class);
        $this->app->bind(LeaveTypeRepositoryInterface::class, LeaveTypeRepository::class);

        // Notification repositories
        $this->app->singleton(NotificationSettingRepositoryInterface::class, NotificationSettingRepository::class);
        $this->app->singleton(NotificationStatusRepositoryInterface::class, NotificationStatusRepository::class);
        $this->app->singleton(NotificationApprovalRepositoryInterface::class, NotificationApprovalRepository::class);

        // Holiday repository
        $this->app->singleton(HolidayRepositoryInterface::class, HolidayRepository::class);

        // Hourly Leave repository
        $this->app->singleton(HourlyLeaveRepositoryInterface::class, HourlyLeaveRepository::class);

        // Suggestion repository
        $this->app->singleton(SuggestionRepositoryInterface::class, SuggestionRepository::class);

        // Complaint repository
        $this->app->singleton(ComplaintRepositoryInterface::class, ComplaintRepository::class);

        // Resignation repository
        $this->app->singleton(ResignationRepositoryInterface::class, ResignationRepository::class);

        // Transfer repository
        $this->app->singleton(TransferRepositoryInterface::class, TransferRepository::class);

        if ($this->app->environment('local') && class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
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
