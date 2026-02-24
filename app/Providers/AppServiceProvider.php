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
use App\Repository\CustodyClearanceRepository;
use App\Repository\Interface\CustodyClearanceRepositoryInterface;
use App\Repository\Interface\ResignationRepositoryInterface;
use App\Repository\ResignationRepository;
use App\Repository\Interface\TransferRepositoryInterface;
use App\Repository\TransferRepository;
use App\Repository\Interface\SupportTicketRepositoryInterface;
use App\Repository\SupportTicketRepository;
use App\Repository\Interface\InternalHelpdeskRepositoryInterface;
use App\Repository\InternalHelpdeskRepository;
use App\Repository\Interface\TrainingRepositoryInterface;
use App\Repository\TrainingRepository;
use App\Repository\Interface\TrainerRepositoryInterface;
use App\Repository\TrainerRepository;
use App\Repository\Interface\TrainingSkillRepositoryInterface;
use App\Repository\TrainingSkillRepository;
use App\Repository\Interface\OfficeShiftRepositoryInterface;
use App\Repository\OfficeShiftRepository;
use App\Repository\Interface\ResidenceRenewalRepositoryInterface;
use App\Repository\ResidenceRenewalRepository;
use App\Services\CacheService;
use App\Services\FileUploadService;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use App\Repository\Interface\TerminationRepositoryInterface;
use App\Repository\TerminationRepository;
use App\Repository\Interface\DepartmentRepositoryInterface;
use App\Repository\DepartmentRepository;
use App\Repository\Interface\DesignationRepositoryInterface;
use App\Repository\DesignationRepository;
use App\Repository\Interface\AnnouncementRepositoryInterface;
use App\Repository\AnnouncementRepository;
use App\Repository\Interface\Recruitment\JobRepositoryInterface;
use App\Repository\Recruitment\JobRepository;
use App\Repository\Interface\Recruitment\CandidateRepositoryInterface;
use App\Repository\Recruitment\CandidateRepository;
use App\Repository\Interface\Recruitment\InterviewRepositoryInterface;
use App\Repository\Recruitment\InterviewRepository;
use Laravel\Passport\Passport;
use App\Repository\Interface\SystemDocumentRepositoryInterface;
use App\Repository\SystemDocumentRepository;
use App\Repository\Interface\OfficialDocumentRepositoryInterface;
use App\Repository\OfficialDocumentRepository;
use App\Repository\Interface\SignatureDocumentRepositoryInterface;
use App\Repository\SignatureDocumentRepository;
use App\Repository\Interface\PayslipRepositoryInterface;
use App\Repository\PayslipRepository;
use App\Repository\Interface\ContractOptionRepositoryInterface;
use App\Repository\ContractOptionRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Employee repository
        $this->app->singleton(EmployeeRepositoryInterface::class, EmployeeRepository::class);

        // User repository
        $this->app->singleton(UserRepositoryInterface::class, UserRepository::class);

        // Attendance repository
        $this->app->singleton(AttendanceRepositoryInterface::class, AttendanceRepository::class);

        // Leave repository
        $this->app->singleton(LeaveRepositoryInterface::class, LeaveRepository::class);

        // Leave Adjustment repository
        $this->app->singleton(LeaveAdjustmentRepositoryInterface::class, LeaveAdjustmentRepository::class);

        // Advance Salary repository
        $this->app->singleton(AdvanceSalaryRepositoryInterface::class, AdvanceSalaryRepository::class);

        // Overtime repository
        $this->app->singleton(OvertimeRepositoryInterface::class, OvertimeRepository::class);

        // Travel repository
        $this->app->singleton(TravelRepositoryInterface::class, TravelRepository::class);

        // Travel Type repository
        $this->app->bind(TravelTypeRepositoryInterface::class, TravelTypeRepository::class);

        // Leave Type repository
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

        // Custody Clearance repository
        $this->app->singleton(CustodyClearanceRepositoryInterface::class, CustodyClearanceRepository::class);

        // Support Ticket repository
        $this->app->singleton(SupportTicketRepositoryInterface::class, SupportTicketRepository::class);

        // Internal Helpdesk repository
        $this->app->singleton(InternalHelpdeskRepositoryInterface::class, InternalHelpdeskRepository::class);

        // Training repository
        $this->app->singleton(TrainingRepositoryInterface::class, TrainingRepository::class);

        // Trainer repository
        $this->app->singleton(TrainerRepositoryInterface::class, TrainerRepository::class);

        // Training Skill repository
        $this->app->singleton(TrainingSkillRepositoryInterface::class, TrainingSkillRepository::class);

        // Report repository
        $this->app->singleton(\App\Repository\Interface\ReportRepositoryInterface::class, \App\Repository\ReportRepository::class);

        // Country repository
        $this->app->bind(\App\Repository\Interface\CountryRepositoryInterface::class, \App\Repository\CountryRepository::class);

        // Branch repository
        $this->app->bind(\App\Repository\Interface\BranchRepositoryInterface::class, \App\Repository\BranchRepository::class);

        // Office Shift repository
        $this->app->singleton(OfficeShiftRepositoryInterface::class, OfficeShiftRepository::class);

        // Cache Service (Singleton)
        $this->app->singleton(CacheService::class, CacheService::class);

        $this->app->bind(FileUploadService::class, FileUploadService::class);

        // Asset Configuration repository
        $this->app->singleton(\App\Repository\Interface\AssetConfigurationRepositoryInterface::class, \App\Repository\AssetConfigurationRepository::class);

        // Asset repository
        $this->app->singleton(\App\Repository\Interface\AssetRepositoryInterface::class, \App\Repository\AssetRepository::class);

        // Award repository
        $this->app->bind(\App\Repository\Interface\AwardRepositoryInterface::class, \App\Repository\AwardRepository::class);

        // Award Configuration repository
        $this->app->bind(\App\Repository\Interface\AwardConfigurationRepositoryInterface::class, \App\Repository\AwardConfigurationRepository::class);

        // Promotion repository
        $this->app->bind(\App\Repository\Interface\PromotionRepositoryInterface::class, \App\Repository\PromotionRepository::class);

        // Termination repository
        $this->app->bind(TerminationRepositoryInterface::class, TerminationRepository::class);

        // Residence Renewal repository
        $this->app->bind(ResidenceRenewalRepositoryInterface::class, ResidenceRenewalRepository::class);

        // Poll repository
        $this->app->bind(\App\Repository\Interface\PollRepositoryInterface::class, \App\Repository\PollRepository::class);

        // Department repository
        $this->app->bind(DepartmentRepositoryInterface::class, DepartmentRepository::class);

        // Designation repository
        $this->app->bind(DesignationRepositoryInterface::class, DesignationRepository::class);

        // Announcement repository
        $this->app->singleton(AnnouncementRepositoryInterface::class, AnnouncementRepository::class);

        // Meeting repository
        $this->app->singleton(\App\Repository\Interface\MeetingRepositoryInterface::class, \App\Repository\MeetingRepository::class);

        // Visitor repository
        $this->app->singleton(\App\Repository\Interface\VisitorRepositoryInterface::class, \App\Repository\VisitorRepository::class);

        // End of Service repository
        $this->app->singleton(\App\Repository\Interface\EndOfServiceRepositoryInterface::class, \App\Repository\EndOfServiceRepository::class);

        // Finance repository
        $this->app->singleton(\App\Repository\Interface\FinanceRepositoryInterface::class, \App\Repository\FinanceRepository::class);

        // Warehouse repository
        $this->app->bind(\App\Repository\Interface\WarehouseRepositoryInterface::class, \App\Repository\WarehouseRepository::class);

        // Supplier repository
        $this->app->bind(\App\Repository\Interface\SupplierRepositoryInterface::class, \App\Repository\SupplierRepository::class);

        // Recruitment - Jobs
        $this->app->bind(JobRepositoryInterface::class, JobRepository::class);

        // Recruitment - Candidates
        $this->app->bind(CandidateRepositoryInterface::class, CandidateRepository::class);

        // Recruitment - Interviews
        $this->app->bind(InterviewRepositoryInterface::class, InterviewRepository::class);

        // General Documents repository
        $this->app->singleton(SystemDocumentRepositoryInterface::class, SystemDocumentRepository::class);

        // Official Documents repository
        $this->app->singleton(OfficialDocumentRepositoryInterface::class, OfficialDocumentRepository::class);

        // Signature Documents repository
        $this->app->singleton(SignatureDocumentRepositoryInterface::class, SignatureDocumentRepository::class);

        // Events repository
        $this->app->singleton(\App\Repository\Interface\EventRepositoryInterface::class, \App\Repository\EventRepository::class);

        // Payroll - Payslips repository
        $this->app->bind(PayslipRepositoryInterface::class, PayslipRepository::class);

        // Contract Options repository
        $this->app->bind(ContractOptionRepositoryInterface::class, ContractOptionRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Configure Passport token expiration
        if (class_exists('Laravel\Passport\Passport')) {
            Passport::tokensExpireIn(now()->addMinutes(15));
            Passport::refreshTokensExpireIn(now()->addMinutes(120)); //120 minutes 
            Passport::personalAccessTokensExpireIn(now()->addMinutes(360));
        }

        // تسجيل Listener لفشل الـ Jobs
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Queue\Events\JobFailed::class,
            \App\Listeners\JobFailedListener::class
        );

        // Scramble::configure()
        //     ->routes(function (Route $route) {
        //         return Str::startsWith($route->uri, 'api/');
        //     });
    }
}
