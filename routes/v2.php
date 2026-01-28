<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\HourlyLeaveController;
use App\Http\Controllers\Api\AdvanceSalaryController;
use App\Http\Controllers\Api\BiometricAttendanceController;
use App\Http\Controllers\Api\LeaveAdjustmentController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\SuggestionController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\EmployeeProfileController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\ResignationController;
use App\Http\Controllers\Api\TransferController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/companies', [AuthController::class, 'getCompanies']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Biometric Device Integration (Public - لا يحتاج تسجيل دخول)
Route::post('/biometric/punch', [BiometricAttendanceController::class, 'punch']);
// Route::post('/biometric/logs', [BiometricAttendanceController::class, 'storeBulkLogs']);
Route::post('/biometric/logs', [BiometricAttendanceController::class, 'storeBulkLogs'])->middleware('fix.biometric.json');
Route::get('/biometric/companies', [BiometricAttendanceController::class, 'getCompaniesWithBranches']);

// Protected routes with simple company isolation
Route::middleware(['auth:api', 'simple.company'])->group(function () {
    // FCM Device Token - تسجيل توكن الجهاز للإشعارات
    Route::post('/user/device-token', [AuthController::class, 'updateDeviceToken']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/user/permissions', [AuthController::class, 'permissions']);
    Route::get('/countries', [\App\Http\Controllers\Api\CountryController::class, 'index']);
    Route::get('/countries/{id}', [\App\Http\Controllers\Api\CountryController::class, 'show']);
    Route::get('/branches', [\App\Http\Controllers\Api\BranchController::class, 'index']);
    Route::get('/branches/{id}', [\App\Http\Controllers\Api\BranchController::class, 'show']);

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [App\Http\Controllers\Api\DashboardController::class, 'getStats']);
        Route::get('/activity', [App\Http\Controllers\Api\DashboardController::class, 'getActivity']);
    });



    
    
    Route::prefix('employees')->group(function () {
        // Employees for duty employee - no permissions required (must come before /employees/{id})
        Route::get('/employees-for-duty-employee', [EmployeeController::class, 'getEmployeesForDutyEmployee']);
        
        // Backup employees based on target employee department
        Route::get('/duty-employees', [EmployeeController::class, 'getDutyEmployeesForEmployee']);
        
        // Employees for notify - returns employees who can receive notifications
        Route::get('/employees-for-notify', [EmployeeController::class, 'getEmployeesForNotify']);
        
        // Employees subordinates - returns employees based on hierarchy and restrictions
        Route::get('/subordinates', [EmployeeController::class, 'getSubordinates']);
        
        // Employee approval levels - returns approval chain for an employee
        Route::get('/approval-levels', [EmployeeController::class, 'getApprovalLevels']);
        
        // Main employee CRUD operations
        Route::get('/', [EmployeeController::class, 'index'])->middleware('simple.permission:staff2');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('simple.permission:staff3');
        Route::get('/{id}', [EmployeeController::class, 'show'])->middleware('simple.permission:staff4')->where('id', '[0-9]+');
        Route::put('/{id}', [EmployeeController::class, 'update'])->middleware('simple.permission:staff4')->where('id', '[0-9]+');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->middleware('simple.permission:staff5')->where('id', '[0-9]+');
        Route::get('/search', [EmployeeController::class, 'search'])->middleware('simple.permission:staff2');
        
        // Employee Profile Data (requires hr_profile permission)
        // Route::get('/statistics', [EmployeeController::class, 'statistics'])->middleware('simple.permission:staff2');
        // Route::get('/{id}/documents', [EmployeeController::class, 'getEmployeeDocuments'])->middleware('simple.permission:hr_documents');
        // Route::get('/{id}/leave-balance', [EmployeeController::class, 'getEmployeeLeaveBalance']);
        // Route::get('/{id}/attendance', [EmployeeController::class, 'getEmployeeAttendance']);
        // Route::get('/{id}/salary-details', [EmployeeController::class, 'getEmployeeSalaryDetails']);
    });
    
    // Employee Management with Simple Permission Checks
    // Employee Profile Update Endpoints
    Route::prefix('employees')->middleware('simple.permission:staff4')->group(function () {
        Route::put('/{id}/change-password', [EmployeeController::class, 'changePassword']);
        Route::post('/{id}/upload-profile-image', [EmployeeController::class, 'uploadProfileImage']);
        Route::post('/{id}/upload-document', [EmployeeController::class, 'uploadDocument']);
        Route::put('/{id}/update-profile-info', [EmployeeController::class, 'updateProfileInfo']);
        Route::put('/{id}/update-cv', [EmployeeController::class, 'updateCV']);
        Route::put('/{id}/update-social-links', [EmployeeController::class, 'updateSocialLinks']);
        Route::put('/{id}/update-bank-info', [EmployeeController::class, 'updateBankInfo']);
        Route::put('/{id}/add-family-data', [EmployeeController::class, 'addFamilyData']);
        Route::delete('/{id}/delete-family-data/{contactId}', [EmployeeController::class, 'deleteFamilyData']);
    });
    

    // Employee Profile Update Endpoints for self
    Route::prefix('profile')->group(function () {
        Route::put('/change-password', [EmployeeProfileController::class, 'changePassword'])->middleware('simple.permission:change_password');
        Route::post('/upload-profile-image', [EmployeeProfileController::class, 'uploadProfileImage'])->middleware('simple.permission:hr_picture');
        Route::post('/upload-document', [EmployeeController::class, 'uploadDocument'])->middleware('simple.permission:hr_documents');
        Route::put('/update-profile-info', [EmployeeController::class, 'updateProfileInfo'])->middleware('simple.permission:account_info');
        Route::put('/update-cv', [EmployeeProfileController::class, 'updateCV'])->middleware('simple.permission:hr_personal_info');
        Route::put('/update-social-links', [EmployeeProfileController::class, 'updateSocialLinks'])->middleware('simple.permission:hr_personal_info');
        Route::put('/update-bank-info', [EmployeeProfileController::class, 'updateBankInfo'])->middleware('simple.permission:hr_personal_info');
        Route::put('/add-family-data', [EmployeeProfileController::class, 'addFamilyData'])->middleware('simple.permission:hr_personal_info');
        Route::delete('/delete-family-data/{contactId}', [EmployeeProfileController::class, 'deleteFamilyData'])->middleware('simple.permission:hr_personal_info');
    });

    // Leave Management with Simple Permission Checks
        Route::prefix('leaves')->group(function () {
            // Leave Applications
            Route::get('/enums', [LeaveController::class, 'getLeaveEnums']);
            Route::get('/applications', [LeaveController::class, 'getApplications']);
            Route::post('/applications', [LeaveController::class, 'createApplication']);
            Route::delete('/applications/{id}/cancel', [LeaveController::class, 'cancelApplication']);
            Route::put('/applications/{id}', [LeaveController::class, 'updateApplication']);
            Route::get('/applications/{id}', [LeaveController::class, 'showApplication']);

            // Leave Adjustments
            Route::get('/adjustments/enums', [LeaveAdjustmentController::class, 'getLeaveAdjustmentsEnums']);
            Route::get('/adjustments', [LeaveAdjustmentController::class, 'getAdjustments']);
            Route::post('/adjustments', [LeaveAdjustmentController::class, 'createAdjustment']);
            Route::delete('/adjustments/{id}/cancel', [LeaveAdjustmentController::class, 'cancelAdjustment']);
            Route::get('/adjustments/{id}', [LeaveAdjustmentController::class, 'showLeaveAdjustment']);
            Route::put('/adjustments/{id}', [LeaveAdjustmentController::class, 'updateAdjustment']);

            
            Route::get('/check-balance', [LeaveController::class, 'checkLeaveBalance']);
            Route::get('/monthly-statistics', [LeaveController::class, 'getMonthlyStatistics']);
            Route::get('/stats', [LeaveController::class, 'getStats']);

            Route::post('/applications/{id}/approve-or-reject', [LeaveController::class, 'approveApplication']);
            Route::post('/adjustments/{id}/approve-or-reject', [LeaveAdjustmentController::class, 'approveAdjustment']);
        });

        // Hourly Leave Management
        Route::prefix('hourly-leaves')->group(function () {
            Route::get('/enums', [HourlyLeaveController::class, 'getEnums']);
            route::apiResource('/', HourlyLeaveController::class);
            Route::delete('/{id}/cancel', [HourlyLeaveController::class, 'cancel']);
            Route::post('/{id}/approve-or-reject', [HourlyLeaveController::class, 'approveOrReject']);
        });

        Route::prefix('leaves-types')->group(function () {
            Route::get('/', [LeaveTypeController::class, 'index']);
            Route::post('/', [LeaveTypeController::class, 'storeLeaveType']);
            Route::get('/{id}', [LeaveTypeController::class, 'showLeaveType']);
            Route::put('/{id}', [LeaveTypeController::class, 'updateLeaveType']);
            Route::delete('/{id}', [LeaveTypeController::class, 'destroyLeaveType']);
        });

    // Advance Salary & Loan Management
    Route::prefix('advances')->group(function () {
        Route::get('/', [AdvanceSalaryController::class, 'index']);
        Route::post('/', [AdvanceSalaryController::class, 'store']);
        Route::post('/tier-based', [AdvanceSalaryController::class, 'storeTierBased']);
        Route::get('/stats', [AdvanceSalaryController::class, 'stats']);
        Route::post('/{id}/approve', [AdvanceSalaryController::class, 'approve']);
        Route::delete('/{id}/cancel', [AdvanceSalaryController::class, 'cancel']);
        Route::get('/{id}', [AdvanceSalaryController::class, 'show']);
        Route::put('/{id}', [AdvanceSalaryController::class, 'update']);
    });
    
    // Loan Eligibility & Tiers (Simplified)
    Route::prefix('loans')->group(function () {
        Route::get('/form-init', [LoanController::class, 'formInit']);
        Route::post('/preview', [LoanController::class, 'preview']);
    });


    // Overtime Management
    Route::prefix('overtime')->group(function () {
        Route::get('/requests', [OvertimeController::class, 'index']);
        Route::post('/requests', [OvertimeController::class, 'store']);
        Route::get('/requests/{id}', [OvertimeController::class, 'show']);
        Route::put('/requests/{id}', [OvertimeController::class, 'update']);
        Route::delete('/requests/{id}', [OvertimeController::class, 'destroy']);
        Route::post('/requests/{id}/approve', [OvertimeController::class, 'approve']);
        Route::post('/requests/{id}/reject', [OvertimeController::class, 'reject']);
        Route::get('/enums', [OvertimeController::class, 'getEnums']);
        Route::get('/requests/pending', [OvertimeController::class, 'pending']);
        Route::get('/requests/team', [OvertimeController::class, 'team']);
    });
    // Route::get('/overtime/stats', [OvertimeController::class, 'stats']);

    // System Logs
    Route::middleware('role:company')->group(function () {
        Route::get('/system-logs', [App\Http\Controllers\Api\SystemLogController::class, 'index']);
    });

    // Attendance records listing and filtering
    Route::get('/attendances', [AttendanceController::class, 'index'])->middleware('simple.permission:attendance');

    // Attendance Management
    Route::middleware('simple.permission:timesheet')->group(function () {
        // Clock in/out operations
        Route::post('/attendances/clock-in', [AttendanceController::class, 'clockIn'])->middleware('simple.permission:upattendance2');
        Route::post('/attendances/clock-out', [AttendanceController::class, 'clockOut'])->middleware('simple.permission:upattendance2');

        // Lunch break operations
        Route::post('/attendances/lunch-break-in', [AttendanceController::class, 'lunchBreakIn'])->middleware('simple.permission:upattendance2');
        Route::post('/attendances/lunch-break-out', [AttendanceController::class, 'lunchBreakOut'])->middleware('simple.permission:upattendance2');

        // Today's status and monthly reports
        Route::get('/attendances/today', [AttendanceController::class, 'getTodayStatus'])->middleware('simple.permission:upattendance2');
        Route::get('/attendances/monthly-report', [AttendanceController::class, 'getMonthlyReport'])->middleware('simple.permission:monthly_time');
        Route::get('/attendances/details', [AttendanceController::class, 'getAttendanceDetails'])->middleware('simple.permission:timesheet');

        // CRUD operations (admin/manager only)
        // Route::get('/attendances/{id}', [AttendanceController::class, 'show'])->middleware('simple.permission:upattendance1');
        Route::put('/attendances/{id}', [AttendanceController::class, 'update'])->middleware('simple.permission:upattendance3');
        Route::delete('/attendances/{id}', [AttendanceController::class, 'destroy'])->middleware('simple.permission:upattendance4');
    });

    // Travel Management
    Route::prefix('travels')->group(function () {
        Route::get('/enums', [App\Http\Controllers\Api\TravelController::class, 'getEnums']);
        Route::get('/', [App\Http\Controllers\Api\TravelController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\TravelController::class, 'storeTravel']);
        Route::get('/search', [App\Http\Controllers\Api\TravelController::class, 'search']);
        Route::get('/{id}', [App\Http\Controllers\Api\TravelController::class, 'showTravel']);
        Route::put('/{id}', [App\Http\Controllers\Api\TravelController::class, 'updateTravel']);
        Route::delete('/{id}', [App\Http\Controllers\Api\TravelController::class, 'cancelTravel']);
        Route::post('/{id}/approve-or-reject', [App\Http\Controllers\Api\TravelController::class, 'approveTravel']);
    });

    // Travel Type Management
    Route::prefix('travel-types')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\TravelTypeController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\TravelTypeController::class, 'storeTravelType']);
        Route::get('/search', [App\Http\Controllers\Api\TravelTypeController::class, 'search']);
        Route::get('/{id}', [App\Http\Controllers\Api\TravelTypeController::class, 'showTravelType']);
        Route::put('/{id}', [App\Http\Controllers\Api\TravelTypeController::class, 'updateTravelType']);
        Route::delete('/{id}', [App\Http\Controllers\Api\TravelTypeController::class, 'destroyTravelType']);
    });

    // Notifications & Approvals
    Route::prefix('notifications')->group(function () {
        // User notifications
        Route::get('/', [App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::get('/unread-count', [App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
        Route::put('/{id}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::put('/mark-all-read', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);

        // Notification settings (Admin only)
        Route::middleware('simple.permission:admin')->group(function () {
            Route::get('/settings/{module}', [App\Http\Controllers\Api\NotificationController::class, 'getSettings']);
            Route::post('/settings', [App\Http\Controllers\Api\NotificationController::class, 'updateSettings']);
        });
    });

    // Approval workflow
    Route::prefix('approvals')->group(function () {
        Route::get('/pending', [App\Http\Controllers\Api\ApprovalController::class, 'getPending']);
        Route::post('/process', [App\Http\Controllers\Api\ApprovalController::class, 'processApproval']);
        Route::get('/history/{module}/{id}', [App\Http\Controllers\Api\ApprovalController::class, 'getHistory']);
    });

    // Holidays Management
    Route::prefix('holidays')->middleware('simple.permission:holiday')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\HolidayController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\HolidayController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\HolidayController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\HolidayController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\HolidayController::class, 'destroy']);
        Route::get('/check/{date}', [App\Http\Controllers\Api\HolidayController::class, 'checkHoliday']);
    });

    // Suggestions Management
    Route::prefix('suggestions')->group(function () {
        Route::get('/', [SuggestionController::class, 'index'])->middleware('simple.permission:suggestions1');
        Route::post('/', [SuggestionController::class, 'store'])->middleware('simple.permission:suggestions2');
        Route::get('/{id}', [SuggestionController::class, 'show'])->middleware('simple.permission:suggestions1');
        Route::put('/{id}', [SuggestionController::class, 'update'])->middleware('simple.permission:suggestions3');
        Route::delete('/{id}', [SuggestionController::class, 'destroy'])->middleware('simple.permission:suggestions4');
        Route::post('/{id}/comments', [SuggestionController::class, 'addComment'])->middleware('simple.permission:suggestions2');
        Route::get('/{id}/comments', [SuggestionController::class, 'getComments'])->middleware('simple.permission:suggestions1');
        Route::delete('/{suggestionId}/comments/{commentId}', [SuggestionController::class, 'deleteComment'])->middleware('simple.permission:suggestions4');
    });

    // Complaints Management
    Route::prefix('complaints')->group(function () {
        Route::get('/', [ComplaintController::class, 'index'])->middleware('simple.permission:complaint1');
        Route::post('/', [ComplaintController::class, 'store'])->middleware('simple.permission:complaint2');
        Route::get('/statuses', [ComplaintController::class, 'getStatuses'])->middleware('simple.permission:complaint1');
        Route::get('/{id}', [ComplaintController::class, 'show'])->middleware('simple.permission:complaint1');
        Route::put('/{id}', [ComplaintController::class, 'update'])->middleware('simple.permission:complaint3');
        Route::delete('/{id}', [ComplaintController::class, 'destroy'])->middleware('simple.permission:complaint4');
        Route::post('/{id}/resolve', [ComplaintController::class, 'resolve'])->middleware('simple.permission:complaint3');
    });

    // Resignations Management
    Route::prefix('resignations')->group(function () {
        Route::get('/', [ResignationController::class, 'index'])->middleware('simple.permission:resignation1');
        Route::post('/', [ResignationController::class, 'store'])->middleware('simple.permission:resignation2');
        Route::get('/statuses', [ResignationController::class, 'getStatuses'])->middleware('simple.permission:resignation1');
        Route::get('/{id}', [ResignationController::class, 'show'])->middleware('simple.permission:resignation1');
        Route::put('/{id}', [ResignationController::class, 'update'])->middleware('simple.permission:resignation3');
        Route::delete('/{id}', [ResignationController::class, 'destroy'])->middleware('simple.permission:resignation4');
        Route::post('/{id}/approve-or-reject', [ResignationController::class, 'approveOrReject'])->middleware('simple.permission:resignation3');
    });

    // Transfers Management
    Route::prefix('transfers')->group(function () {
        // Routes المحددة أولاً (قبل {id})
        Route::get('/', [TransferController::class, 'index'])->middleware('simple.permission:transfers1');
        Route::get('/statuses', [TransferController::class, 'getStatuses'])->middleware('simple.permission:transfers1');
        Route::get('/available-companies', [TransferController::class, 'getCompaniesWithBranches'])->middleware('simple.permission:transfers1');
        Route::get('/branches', [TransferController::class, 'getBranches'])->middleware('simple.permission:transfers1');
        // Route::get('/employees', [TransferController::class, 'getTransferableEmployees'])->middleware('simple.permission:transfers1');

        // Create routes
        Route::post('/internal', [TransferController::class, 'storeInternal'])->middleware('simple.permission:transfers2');
        Route::post('/branch', [TransferController::class, 'storeBranch'])->middleware('simple.permission:transfers2');
        Route::post('/intercompany', [TransferController::class, 'storeIntercompany'])->middleware('simple.permission:transfers2');

        // Update routes
        Route::put('/internal/{id}', [TransferController::class, 'updateInternal'])->middleware('simple.permission:transfers3');
        Route::put('/branch/{id}', [TransferController::class, 'updateBranch'])->middleware('simple.permission:transfers3');
        Route::put('/intercompany/{id}', [TransferController::class, 'updateIntercompany'])->middleware('simple.permission:transfers3');

        // Routes العامة بـ {id} في الآخر
        Route::get('/{id}', [TransferController::class, 'show'])->middleware('simple.permission:transfers1');
        Route::get('/{id}/pre-transfer-validation', [TransferController::class, 'getPreTransferValidation'])->middleware('simple.permission:transfers1');
        Route::delete('/{id}', [TransferController::class, 'destroy'])->middleware('simple.permission:transfers4');
        Route::post('/{id}/approve-or-reject', [TransferController::class, 'approveOrReject'])->middleware('simple.permission:transfers3');
        Route::post('/{id}/approve-current-company', [TransferController::class, 'approveByCurrentCompany'])->middleware('simple.permission:transfers3');
        Route::post('/{id}/approve-new-company', [TransferController::class, 'approveByNewCompany'])->middleware('simple.permission:transfers3');
    });

    // Custody Clearance Management - إخلاء طرف العهد
    Route::get('/assets', [App\Http\Controllers\Api\CustodyClearanceController::class, 'getAssets'])->middleware('simple.permission:hr_assets');
    Route::get('/custodies', [App\Http\Controllers\Api\CustodyClearanceController::class, 'getCustodies'])->middleware('simple.permission:hr_custody_clearance1');
    Route::prefix('custody-clearances')->group(function () {
        Route::get('/types', [App\Http\Controllers\Api\CustodyClearanceController::class, 'getClearanceTypes'])->middleware('simple.permission:hr_custody_clearance1');
        Route::get('/', [App\Http\Controllers\Api\CustodyClearanceController::class, 'index'])->middleware('simple.permission:hr_custody_clearance1');
        Route::post('/', [App\Http\Controllers\Api\CustodyClearanceController::class, 'store'])->middleware('simple.permission:hr_custody_clearance2');
        Route::get('/{id}', [App\Http\Controllers\Api\CustodyClearanceController::class, 'show'])->middleware('simple.permission:hr_custody_clearance1');
        Route::post('/{id}/approve-or-reject', [App\Http\Controllers\Api\CustodyClearanceController::class, 'approveOrReject'])->middleware('simple.permission:hr_custody_clearance5');
    });

    // Jobs Monitor (للشركات فقط)
    Route::prefix('jobs')->group(function () {
        Route::get('/stats', [\App\Http\Controllers\Api\JobsMonitorController::class, 'getStats']);
        Route::get('/failed', [\App\Http\Controllers\Api\JobsMonitorController::class, 'getFailedJobs']);
        Route::post('/retry/{uuid}', [\App\Http\Controllers\Api\JobsMonitorController::class, 'retryJob']);
        Route::post('/retry-all', [\App\Http\Controllers\Api\JobsMonitorController::class, 'retryAll']);
        Route::delete('/failed', [\App\Http\Controllers\Api\JobsMonitorController::class, 'clearFailed']);
    });

    // Support Tickets Management - تذاكر الدعم الفني
    Route::prefix('support-tickets')->group(function () {
        Route::get('/enums', [\App\Http\Controllers\Api\SupportTicketController::class, 'getEnums'])->middleware('simple.permission:helpdesk1');
        Route::get('/', [\App\Http\Controllers\Api\SupportTicketController::class, 'index'])->middleware('simple.permission:helpdesk1');
        Route::post('/', [\App\Http\Controllers\Api\SupportTicketController::class, 'store'])->middleware('simple.permission:helpdesk2');
        Route::get('/{id}', [\App\Http\Controllers\Api\SupportTicketController::class, 'show'])->middleware('simple.permission:helpdesk1');
        Route::put('/{id}', [\App\Http\Controllers\Api\SupportTicketController::class, 'update'])->middleware('simple.permission:helpdesk3');
        Route::delete('/{id}', [\App\Http\Controllers\Api\SupportTicketController::class, 'destroy'])->middleware('simple.permission:helpdesk5');
        Route::post('/{id}/close', [\App\Http\Controllers\Api\SupportTicketController::class, 'close'])->middleware('simple.permission:helpdesk6');
        Route::post('/{id}/reopen', [\App\Http\Controllers\Api\SupportTicketController::class, 'reopen'])->middleware('simple.permission:helpdesk6');
        Route::get('/{id}/replies', [\App\Http\Controllers\Api\SupportTicketController::class, 'getReplies'])->middleware('simple.permission:helpdesk1');
        Route::post('/{id}/replies', [\App\Http\Controllers\Api\SupportTicketController::class, 'addReply'])->middleware('simple.permission:helpdesk2');
    });

    // Internal Helpdesk - التذاكر الداخلية للدعم الفني
    Route::prefix('internal-helpdesk')->group(function () {
        Route::get('/enums', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'getEnums'])->middleware('simple.permission:helpdesk1');
        Route::get('/departments', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'getDepartments'])->middleware('simple.permission:helpdesk1');
        Route::get('/employees/{departmentId}', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'getEmployees'])->middleware('simple.permission:helpdesk1');
        Route::get('/', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'index'])->middleware('simple.permission:helpdesk1');
        Route::post('/', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'store'])->middleware('simple.permission:helpdesk2');
        Route::get('/{id}', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'show'])->middleware('simple.permission:helpdesk1');
        Route::put('/{id}', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'update'])->middleware('simple.permission:helpdesk3');
        Route::delete('/{id}', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'destroy'])->middleware('simple.permission:helpdesk5');
        Route::post('/{id}/close', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'close'])->middleware('simple.permission:helpdesk6');
        Route::post('/{id}/reopen', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'reopen'])->middleware('simple.permission:helpdesk6');
        Route::get('/{id}/replies', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'getReplies'])->middleware('simple.permission:helpdesk1');
        Route::post('/{id}/replies', [\App\Http\Controllers\Api\InternalHelpdeskController::class, 'addReply'])->middleware('simple.permission:helpdesk2');
    });

    // Training Management - إدارة التدريب
    Route::prefix('trainings')->group(function () {
        Route::get('/enums', [\App\Http\Controllers\Api\TrainingController::class, 'enums'])->middleware('simple.permission:training1');
        Route::get('/statistics', [\App\Http\Controllers\Api\TrainingController::class, 'statistics'])->middleware('simple.permission:training1');
        Route::get('/', [\App\Http\Controllers\Api\TrainingController::class, 'index'])->middleware('simple.permission:training1');
        Route::post('/', [\App\Http\Controllers\Api\TrainingController::class, 'store'])->middleware('simple.permission:training2');
        Route::get('/{id}', [\App\Http\Controllers\Api\TrainingController::class, 'show'])->middleware('simple.permission:training1');
        Route::put('/{id}', [\App\Http\Controllers\Api\TrainingController::class, 'update'])->middleware('simple.permission:training3');
        Route::delete('/{id}', [\App\Http\Controllers\Api\TrainingController::class, 'destroy'])->middleware('simple.permission:training4');
        Route::patch('/{id}/status', [\App\Http\Controllers\Api\TrainingController::class, 'updateStatus'])->middleware('simple.permission:training3');
        Route::get('/{id}/notes', [\App\Http\Controllers\Api\TrainingController::class, 'getNotes'])->middleware('simple.permission:training1');
        Route::post('/{id}/notes', [\App\Http\Controllers\Api\TrainingController::class, 'addNote'])->middleware('simple.permission:training2');
    });

    // Trainer Management - إدارة المدربين
    Route::prefix('trainers')->group(function () {
        Route::get('/dropdown', [\App\Http\Controllers\Api\TrainerController::class, 'dropdown'])->middleware('simple.permission:training1');
        Route::get('/', [\App\Http\Controllers\Api\TrainerController::class, 'index'])->middleware('simple.permission:trainer1');
        Route::post('/', [\App\Http\Controllers\Api\TrainerController::class, 'store'])->middleware('simple.permission:trainer2');
        Route::get('/{id}', [\App\Http\Controllers\Api\TrainerController::class, 'show'])->middleware('simple.permission:trainer1');
        Route::put('/{id}', [\App\Http\Controllers\Api\TrainerController::class, 'update'])->middleware('simple.permission:trainer3');
        Route::delete('/{id}', [\App\Http\Controllers\Api\TrainerController::class, 'destroy'])->middleware('simple.permission:trainer4');
    });

    // Training Skills (Training Types) - أنواع التدريب (من ci_erp_constants)
    Route::prefix('training-skills')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\TrainingSkillController::class, 'index'])->middleware('simple.permission:training_skill1');
        Route::post('/', [\App\Http\Controllers\Api\TrainingSkillController::class, 'store'])->middleware('simple.permission:training_skill2');
        Route::put('/{id}', [\App\Http\Controllers\Api\TrainingSkillController::class, 'update'])->middleware('simple.permission:training_skill3');
        Route::delete('/{id}', [\App\Http\Controllers\Api\TrainingSkillController::class, 'destroy'])->middleware('simple.permission:training_skill4');
    });

    // Reports Management - صلاحية موحدة: system_reports
    Route::prefix('reports')->middleware('simple.permission:system_reports')->group(function () {
        // Options
        Route::get('/options', [\App\Http\Controllers\Api\ReportController::class, 'options']);

        // Attendance Reports
        Route::get('/attendance/monthly', [\App\Http\Controllers\Api\ReportController::class, 'attendanceMonthly']);
        Route::get('/attendance/first-last', [\App\Http\Controllers\Api\ReportController::class, 'attendanceFirstLast']);
        Route::get('/attendance/time-records', [\App\Http\Controllers\Api\ReportController::class, 'attendanceTimeRecords']);
        Route::get('/attendance/date-range', [\App\Http\Controllers\Api\ReportController::class, 'attendanceDateRange']);

        // Timesheet Report
        Route::get('/timesheet', [\App\Http\Controllers\Api\ReportController::class, 'timesheet']);

        // Financial Reports
        Route::get('/payroll', [\App\Http\Controllers\Api\ReportController::class, 'payroll']);
        Route::get('/loans', [\App\Http\Controllers\Api\ReportController::class, 'loans']);

        // HR Reports
        Route::get('/leaves', [\App\Http\Controllers\Api\ReportController::class, 'leaves']);
        Route::get('/awards', [\App\Http\Controllers\Api\ReportController::class, 'awards']);
        Route::get('/promotions', [\App\Http\Controllers\Api\ReportController::class, 'promotions']);
        Route::get('/resignations', [\App\Http\Controllers\Api\ReportController::class, 'resignations']);
        Route::get('/terminations', [\App\Http\Controllers\Api\ReportController::class, 'terminations']);
        Route::get('/transfers', [\App\Http\Controllers\Api\ReportController::class, 'transfers']);

        // Document Expiry Reports
        Route::get('/residence-renewals', [\App\Http\Controllers\Api\ReportController::class, 'residenceRenewals']);
        Route::get('/expiring-contracts', [\App\Http\Controllers\Api\ReportController::class, 'expiringContracts']);
        Route::get('/expiring-documents', [\App\Http\Controllers\Api\ReportController::class, 'expiringDocuments']);

        // Employee Reports
        Route::get('/employees-by-branch', [\App\Http\Controllers\Api\ReportController::class, 'employeesByBranch']);
        Route::get('/employees-by-country', [\App\Http\Controllers\Api\ReportController::class, 'employeesByCountry']);


        // End of Service
        Route::get('/end-of-service', [\App\Http\Controllers\Api\ReportController::class, 'endOfService']);

        // ==========================================
        // Async Reports (Queue-based)
        // ==========================================
        Route::post('generate-async/{type}', [\App\Http\Controllers\Api\AsyncReportController::class, 'generateAsync']);
        Route::get('generated', [\App\Http\Controllers\Api\AsyncReportController::class, 'generatedReports']);
        Route::get('generated/{id}/download', [\App\Http\Controllers\Api\AsyncReportController::class, 'downloadGenerated']);
        Route::delete('generated/{id}', [\App\Http\Controllers\Api\AsyncReportController::class, 'deleteGenerated']);
    });
});
