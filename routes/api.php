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
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/user/permissions', [AuthController::class, 'permissions']);

    // Employee management
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/stats', [EmployeeController::class, 'stats']);
    Route::get('/employees/search', [EmployeeController::class, 'search']);
    Route::get('/employees/by-type/{type}', [EmployeeController::class, 'getByType']);

    // Employees for duty employee - no permissions required (must come before /employees/{id})
    Route::get('/employees/employees-for-duty-employee', [EmployeeController::class, 'getEmployeesForDutyEmployee']);

    // Backup employees based on target employee department
    Route::get('/employees/duty-employees', [EmployeeController::class, 'getDutyEmployeesForEmployee']);

    // Employees for notify - returns employees who can receive notifications
    Route::get('/employees/employees-for-notify', [EmployeeController::class, 'getEmployeesForNotify']);

    // Employees subordinates - returns employees based on hierarchy and restrictions
    Route::get('/employees/subordinates', [EmployeeController::class, 'getSubordinates']);

    // Employee approval levels - returns approval chain for an employee
    Route::get('/employees/approval-levels', [EmployeeController::class, 'getApprovalLevels']);

    Route::get('/employees/{id}', [EmployeeController::class, 'show']);

    // Employee filters and exports
    Route::get('/employees/export/pdf', [EmployeeController::class, 'exportPdf']);
    Route::get('/employees/export/pdf/detailed', [EmployeeController::class, 'exportDetailedPdf']);
    Route::get('/employees/export/pdf/arabic', [EmployeeController::class, 'exportArabicPdf']);
    Route::get('/employees/export/pdf/arabic-full', [EmployeeController::class, 'exportFullArabicPdf']);
    Route::get('/employees/active', [EmployeeController::class, 'getActiveEmployees']);
    Route::get('/employees/inactive', [EmployeeController::class, 'getInactiveEmployees']);


    // Employee CRUD - require admin/hr roles only
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);



    // Leave Management with Simple Permission Checks
    Route::middleware('simple.permission:hr_leave')->group(function () {
        Route::get('/leaves/enums', [LeaveController::class, 'getLeaveEnums']);
        Route::get('/leaves/applications', [LeaveController::class, 'getApplications']);
        Route::post('/leaves/applications', [LeaveController::class, 'createApplication']);
        Route::delete('/leaves/applications/{id}/cancel', [LeaveController::class, 'cancelApplication']);
        Route::put('/leaves/applications/{id}', [LeaveController::class, 'updateApplication']);
        Route::get('/leaves/applications/{id}', [LeaveController::class, 'showApplication']);

        // Hourly Leave Management
        Route::get('/hourly-leaves/enums', [HourlyLeaveController::class, 'getEnums']);
        route::apiResource('/hourly-leaves', HourlyLeaveController::class);
        Route::delete('/hourly-leaves/{id}/cancel', [HourlyLeaveController::class, 'cancel']);
        Route::post('/hourly-leaves/{id}/approve-or-reject', [HourlyLeaveController::class, 'approveOrReject']);

        Route::get('/leaves/adjustments/enums', [LeaveAdjustmentController::class, 'getLeaveAdjustmentsEnums']);
        Route::get('/leaves/adjustments', [LeaveAdjustmentController::class, 'getAdjustments']);
        Route::post('/leaves/adjustments', [LeaveAdjustmentController::class, 'createAdjustment']);
        Route::delete('/leaves/adjustments/{id}/cancel', [LeaveAdjustmentController::class, 'cancelAdjustment']);
        Route::get('/leaves/adjustments/{id}', [LeaveAdjustmentController::class, 'showLeaveAdjustment']);
        Route::put('/leaves/adjustments/{id}', [LeaveAdjustmentController::class, 'updateAdjustment']);

        Route::get('/leave-types', [LeaveTypeController::class, 'index']);
        Route::post('/leave-types', [LeaveTypeController::class, 'storeLeaveType']);
        Route::get('/leave-types/{id}', [LeaveTypeController::class, 'showLeaveType']);
        Route::put('/leave-types/{id}', [LeaveTypeController::class, 'updateLeaveType']);
        Route::delete('/leave-types/{id}', [LeaveTypeController::class, 'destroyLeaveType']);
        // Leave balance check 
        Route::get('/leaves/check-balance', [LeaveController::class, 'checkLeaveBalance']);
        Route::get('/leaves/monthly-statistics', [LeaveController::class, 'getMonthlyStatistics']);
        Route::get('/leaves/stats', [LeaveController::class, 'getStats']);

        Route::post('/leaves/applications/{id}/approve-or-reject', [LeaveController::class, 'approveApplication']);
        Route::post('/leaves/adjustments/{id}/approve-or-reject', [LeaveAdjustmentController::class, 'approveAdjustment']);
    });

    // Advance Salary & Loan Management
    Route::middleware('simple.permission:hradvance_salary')->group(function () {
        Route::get('/advances', [AdvanceSalaryController::class, 'index']);
        Route::post('/advances', [AdvanceSalaryController::class, 'store']);
        Route::post('/advances/tier-based', [AdvanceSalaryController::class, 'storeTierBased']);
        Route::get('/advances/stats', [AdvanceSalaryController::class, 'stats']);
        Route::post('/advances/{id}/approve', [AdvanceSalaryController::class, 'approve']);
        Route::delete('/advances/{id}/cancel', [AdvanceSalaryController::class, 'cancel']);
        Route::get('/advances/{id}', [AdvanceSalaryController::class, 'show']);
        Route::put('/advances/{id}', [AdvanceSalaryController::class, 'update']);
        // Loan Eligibility & Tiers (Simplified)
        Route::get('/loans/form-init', [\App\Http\Controllers\Api\LoanController::class, 'formInit']);
        Route::post('/loans/preview', [\App\Http\Controllers\Api\LoanController::class, 'preview']);
    });



    // Overtime Management
    Route::middleware('simple.permission:overtime_req1')->group(function () {
        Route::get('/overtime/requests', [OvertimeController::class, 'index']);
        Route::post('/overtime/requests', [OvertimeController::class, 'store']);
        Route::get('/overtime/requests/{id}', [OvertimeController::class, 'show']);
        Route::put('/overtime/requests/{id}', [OvertimeController::class, 'update']);
        Route::delete('/overtime/requests/{id}', [OvertimeController::class, 'destroy']);
        Route::post('/overtime/requests/{id}/approve', [OvertimeController::class, 'approve']);
        Route::post('/overtime/requests/{id}/reject', [OvertimeController::class, 'reject']);
    });
    Route::get('/overtime/enums', [OvertimeController::class, 'getEnums']);
    Route::get('/overtime/requests/pending', [OvertimeController::class, 'pending']);
    Route::get('/overtime/requests/team', [OvertimeController::class, 'team']);
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
    Route::middleware('simple.permission:hr_travel')->group(function () {
        Route::get('/travels/enums', [App\Http\Controllers\Api\TravelController::class, 'getEnums']);
        Route::get('/travels', [App\Http\Controllers\Api\TravelController::class, 'index']);
        Route::post('/travels', [App\Http\Controllers\Api\TravelController::class, 'storeTravel']);
        Route::get('/travels/search', [App\Http\Controllers\Api\TravelController::class, 'search']);
        Route::get('/travels/{id}', [App\Http\Controllers\Api\TravelController::class, 'showTravel']);
        Route::put('/travels/{id}', [App\Http\Controllers\Api\TravelController::class, 'updateTravel']);
        Route::delete('/travels/{id}', [App\Http\Controllers\Api\TravelController::class, 'cancelTravel']);
        Route::post('/travels/{id}/approve-or-reject', [App\Http\Controllers\Api\TravelController::class, 'approveTravel']);

        // Travel Type Management
        Route::get('/travel-types', [App\Http\Controllers\Api\TravelTypeController::class, 'index']);
        Route::post('/travel-types', [App\Http\Controllers\Api\TravelTypeController::class, 'storeTravelType']);
        Route::get('/travel-types/search', [App\Http\Controllers\Api\TravelTypeController::class, 'search']);
        Route::get('/travel-types/{id}', [App\Http\Controllers\Api\TravelTypeController::class, 'showTravelType']);
        Route::put('/travel-types/{id}', [App\Http\Controllers\Api\TravelTypeController::class, 'updateTravelType']);
        Route::delete('/travel-types/{id}', [App\Http\Controllers\Api\TravelTypeController::class, 'destroyTravelType']);
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
    Route::prefix('suggestions')->middleware('simple.permission:hr_suggestions')->group(function () {
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
    Route::prefix('complaints')->middleware('simple.permission:hr_complaints')->group(function () {
        Route::get('/', [ComplaintController::class, 'index'])->middleware('simple.permission:complaint1');
        Route::post('/', [ComplaintController::class, 'store'])->middleware('simple.permission:complaint2');
        Route::get('/statuses', [ComplaintController::class, 'getStatuses'])->middleware('simple.permission:complaint1');
        Route::get('/{id}', [ComplaintController::class, 'show'])->middleware('simple.permission:complaint1');
        Route::put('/{id}', [ComplaintController::class, 'update'])->middleware('simple.permission:complaint3');
        Route::delete('/{id}', [ComplaintController::class, 'destroy'])->middleware('simple.permission:complaint4');
        Route::post('/{id}/resolve', [ComplaintController::class, 'resolve'])->middleware('simple.permission:complaint3');
    });

    // Resignations Management
    Route::prefix('resignations')->middleware('simple.permission:hr_resignations')->group(function () {
        Route::get('/', [ResignationController::class, 'index'])->middleware('simple.permission:resignation1');
        Route::post('/', [ResignationController::class, 'store'])->middleware('simple.permission:resignation2');
        Route::get('/statuses', [ResignationController::class, 'getStatuses'])->middleware('simple.permission:resignation1');
        Route::get('/{id}', [ResignationController::class, 'show'])->middleware('simple.permission:resignation1');
        Route::put('/{id}', [ResignationController::class, 'update'])->middleware('simple.permission:resignation3');
        Route::delete('/{id}', [ResignationController::class, 'destroy'])->middleware('simple.permission:resignation4');
        Route::post('/{id}/approve-or-reject', [ResignationController::class, 'approveOrReject'])->middleware('simple.permission:resignation3');
    });

    // Transfers Management
    Route::prefix('transfers')->middleware('simple.permission:hr_transfers')->group(function () {
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
    Route::get('/assets', [App\Http\Controllers\Api\CustodyClearanceController::class, 'getAssets']);
    Route::get('/custodies', [App\Http\Controllers\Api\CustodyClearanceController::class, 'getCustodies']);
    Route::prefix('custody-clearances')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\CustodyClearanceController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\CustodyClearanceController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\CustodyClearanceController::class, 'show']);
        Route::post('/{id}/approve-or-reject', [App\Http\Controllers\Api\CustodyClearanceController::class, 'approveOrReject']);
    });

    // Jobs Monitor (للشركات فقط)
    Route::prefix('jobs')->group(function () {
        Route::get('/stats', [\App\Http\Controllers\Api\JobsMonitorController::class, 'getStats']);
        Route::get('/failed', [\App\Http\Controllers\Api\JobsMonitorController::class, 'getFailedJobs']);
        Route::post('/retry/{uuid}', [\App\Http\Controllers\Api\JobsMonitorController::class, 'retryJob']);
        Route::post('/retry-all', [\App\Http\Controllers\Api\JobsMonitorController::class, 'retryAll']);
        Route::delete('/failed', [\App\Http\Controllers\Api\JobsMonitorController::class, 'clearFailed']);
    });
});
