<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\AdvanceSalaryController;
use App\Http\Controllers\Api\LeaveAdjustmentController;
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


// Protected routes with simple company isolation
Route::middleware(['auth:api', 'simple.company'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // Employee management
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/stats', [EmployeeController::class, 'stats']);
    Route::get('/employees/search', [EmployeeController::class, 'search']);
    Route::get('/employees/by-type/{type}', [EmployeeController::class, 'getByType']);
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
    Route::get('/leaves/applications', [LeaveController::class, 'getApplications']);
    Route::post('/leaves/applications', [LeaveController::class, 'createApplication']);
    // Note: More specific routes must come before general ones
    Route::delete('/leaves/applications/{id}/cancel', [LeaveController::class, 'cancelApplication']);
    Route::put('/leaves/applications/{id}', [LeaveController::class, 'updateApplication']);
    Route::get('/leaves/applications/{id}', [LeaveController::class, 'showApplication']);

    Route::get('/leaves/adjustments', [LeaveAdjustmentController::class, 'getAdjustments']);
    Route::post('/leaves/adjustments', [LeaveAdjustmentController::class, 'createAdjustment']);
    // Note: More specific routes must come before general ones
    Route::delete('/leaves/adjustments/{id}/cancel', [LeaveAdjustmentController::class, 'cancelAdjustment']);
    Route::get('/leaves/adjustments/{id}', [LeaveAdjustmentController::class, 'showLeaveAdjustment']);
    Route::put('/leaves/adjustments/{id}', [LeaveAdjustmentController::class, 'updateAdjustment']);

    Route::get('/leaves/types', [LeaveController::class, 'getLeaveTypes']);
    Route::post('/leaves/types', [LeaveController::class, 'createLeaveType']);
    Route::put('/leaves/types/{id}', [LeaveController::class, 'updateLeaveType']);
    Route::delete('/leaves/types/{id}', [LeaveController::class, 'deleteLeaveType']);

    // Leave balance check & settlement
    Route::get('/leaves/check-balance', [LeaveController::class, 'checkLeaveBalance']);
    Route::get('/leaves/monthly-statistics', [LeaveController::class, 'getMonthlyStatistics']);
    // Route::post('/leaves/settlement', [LeaveController::class, 'settleLeave']);
    Route::get('/leaves/stats', [LeaveController::class, 'getStats']);
    Route::post('/leaves/applications/{id}/approve-or-reject', [LeaveController::class, 'approveApplication']);
    Route::post('/leaves/adjustments/{id}/approve-or-reject', [LeaveAdjustmentController::class, 'approveAdjustment']);


    // Advance Salary & Loan Management
    Route::get('/advances', [AdvanceSalaryController::class, 'index']);
    Route::post('/advances', [AdvanceSalaryController::class, 'store']);

    // Manager/HR only endpoints for advance salary/loan management (must come before {id} routes)
    Route::middleware('role:company,admin,hr,manager')->group(function () {
        Route::get('/advances/stats', [AdvanceSalaryController::class, 'stats']);
        Route::post('/advances/{id}/approve', [AdvanceSalaryController::class, 'approve']);
    });

    // Note: More specific routes must come before general ones
    Route::delete('/advances/{id}/cancel', [AdvanceSalaryController::class, 'cancel']);
    Route::get('/advances/{id}', [AdvanceSalaryController::class, 'show']);
    Route::put('/advances/{id}', [AdvanceSalaryController::class, 'update']);

    // Leave Type Management
    Route::get('/leave-types', [App\Http\Controllers\Api\LeaveTypeController::class, 'index']);
    Route::post('/leave-types', [App\Http\Controllers\Api\LeaveTypeController::class, 'storeLeaveType']);
    Route::get('/leave-types/{id}', [App\Http\Controllers\Api\LeaveTypeController::class, 'showLeaveType']);
    Route::put('/leave-types/{id}', [App\Http\Controllers\Api\LeaveTypeController::class, 'updateLeaveType']);
    Route::delete('/leave-types/{id}', [App\Http\Controllers\Api\LeaveTypeController::class, 'destroyLeaveType']);

    // System Logs
    Route::middleware('role:company')->group(function () {
        Route::get('/system-logs', [App\Http\Controllers\Api\SystemLogController::class, 'index']);
    });

    // Travel Management
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
