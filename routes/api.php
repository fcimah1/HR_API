<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveController;
use Illuminate\Http\Request;
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

// Test route for Swagger
Route::post('/test-leave', [LeaveController::class, 'createApplication']);

// Protected routes with simple company isolation
Route::middleware(['auth:sanctum', 'simple.company'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    
    // Employee routes - require specific roles
    Route::middleware('role:company,admin,hr,manager')->group(function () {
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
    });

    // Employee CRUD - require admin/hr roles only
    Route::middleware('role:company,admin,hr')->group(function () {
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{id}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
    });

    // Employee Request routes will be added later when EmployeeRequestController is created

    // Leave Management with Simple Permission Checks
    Route::get('/leaves/applications', [LeaveController::class, 'getApplications']);
    Route::post('/leaves/applications', [LeaveController::class, 'createApplication'])
        ->middleware('simple.permission:leave.create');
    // Note: More specific routes must come before general ones
    Route::delete('/leaves/applications/{id}/cancel', [LeaveController::class, 'cancelApplication']);
    Route::get('/leaves/applications/{id}', [LeaveController::class, 'showApplication']);
    Route::put('/leaves/applications/{id}', [LeaveController::class, 'updateApplication']);
    Route::delete('/leaves/applications/{id}', [LeaveController::class, 'deleteApplication']);
    
    Route::get('/leaves/adjustments', [LeaveController::class, 'getAdjustments']);
    Route::post('/leaves/adjustments', [LeaveController::class, 'createAdjustment']);
    // Note: More specific routes must come before general ones
    Route::delete('/leaves/adjustments/{id}/cancel', [LeaveController::class, 'cancelAdjustment']);
    Route::put('/leaves/adjustments/{id}', [LeaveController::class, 'updateAdjustment']);
    Route::delete('/leaves/adjustments/{id}', [LeaveController::class, 'deleteAdjustment']);
    
    Route::get('/leaves/types', [LeaveController::class, 'getLeaveTypes']);
    Route::post('/leaves/types', [LeaveController::class, 'createLeaveType'])->middleware('role:company,admin,hr,manager');
    
    // Manager/HR only endpoints for leave management
    Route::middleware('role:company,admin,hr,manager')->group(function () {
        Route::post('/leaves/applications/{id}/approve', [LeaveController::class, 'approveApplication']);
        Route::post('/leaves/adjustments/{id}/approve', [LeaveController::class, 'approveAdjustment']);
        Route::get('/leaves/stats', [LeaveController::class, 'getStats']);
    });
});
