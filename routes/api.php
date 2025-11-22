<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\AdvanceSalaryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AssetController;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
    Route::put('/leaves/applications/{id}', [LeaveController::class, 'updateApplication']);
    Route::get('/leaves/applications/{id}', [LeaveController::class, 'showApplication']);
    
    Route::get('/leaves/adjustments', [LeaveController::class, 'getAdjustments']);
    Route::post('/leaves/adjustments',[LeaveController::class, 'createAdjustment']);
    // Note: More specific routes must come before general ones
    Route::delete('/leaves/adjustments/{id}/cancel', [LeaveController::class, 'cancelAdjustment']);
    Route::put('/leaves/adjustments/{id}', [LeaveController::class, 'updateAdjustment']);
    
    Route::get('/leaves/types', [LeaveController::class, 'getLeaveTypes']);
    Route::post('/leaves/types', [LeaveController::class, 'createLeaveType'])->middleware('role:company,admin,hr,manager');
    
    // Manager/HR only endpoints for leave management
    Route::middleware('role:company,admin,hr,manager')->group(function () {
        Route::post('/leaves/applications/{id}/approve', [LeaveController::class, 'approveApplication']);
        Route::post('/leaves/adjustments/{id}/approve', [LeaveController::class, 'approveAdjustment']);
        Route::get('/leaves/stats', [LeaveController::class, 'getStats']);
    });

    // Advance Salary & Loan Management
    // Note: Permission checks for POST/PUT/DELETE are handled in controllers/services
    // because they need to check different permissions based on salary_type (loan vs advance)
    Route::get('/advances', [AdvanceSalaryController::class, 'index']);
    Route::post('/advances', [AdvanceSalaryController::class, 'store']);
    
    // Manager/HR only endpoints for advance salary/loan management (must come before {id} routes)
    // Permission checks are handled in controller based on request type
    Route::get('/advances/stats', [AdvanceSalaryController::class, 'stats']);
    Route::post('/advances/{id}/approve', [AdvanceSalaryController::class, 'approve']);
    
    // Note: More specific routes must come before general ones
    Route::delete('/advances/{id}/cancel', [AdvanceSalaryController::class, 'cancel']);
    Route::get('/advances/{id}', [AdvanceSalaryController::class, 'show']);
    Route::put('/advances/{id}', [AdvanceSalaryController::class, 'update']);

     // ========================================
    // Asset Management Routes
    // ========================================
    
    // Routes accessible to all authenticated employees
    Route::get('/assets/my-assets', [AssetController::class, 'myAssets']);
    Route::get('/assets/categories', [AssetController::class, 'categories']);
    Route::get('/assets/brands', [AssetController::class, 'brands']);
    
    // Report asset issue - all employees can report their assigned assets
    Route::post('/assets/{id}/report-fixing', [AssetController::class, 'reportFixing']);
    
    // Asset viewing (service handles permission-based filtering)
    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/{id}', [AssetController::class, 'show']);
    
    // HR/Manager only endpoints - permission checks handled in service
    // Stats MUST be before general routes to avoid conflicts
    Route::get('/assets/stats', [AssetController::class, 'stats']);
    
    // Asset CRUD operations (NO DELETE per requirements)
    Route::post('/assets', [AssetController::class, 'store'])->middleware('simple.permission:asset2');
    Route::put('/assets/{id}', [AssetController::class, 'update'])->middleware('simple.permission:asset3');
    
    // Assignment operations - require parent permission
    Route::post('/assets/{id}/assign', [AssetController::class, 'assign'])->middleware('simple.permission:hr_assets');
    Route::post('/assets/{id}/unassign', [AssetController::class, 'unassign'])->middleware('simple.permission:hr_assets');
    
    // Get assets by employee - require parent permission
    Route::get('/assets/employee/{employeeId}', [AssetController::class, 'getByEmployee'])->middleware('simple.permission:hr_assets');
    
    // Asset history - require parent permission
    Route::get('/assets/{id}/history', [AssetController::class, 'history'])->middleware('simple.permission:hr_assets');
    
    // Bulk operations - require parent permission
    Route::post('/assets/bulk-assign', [AssetController::class, 'bulkAssign'])->middleware('simple.permission:hr_assets');
    Route::post('/assets/bulk-unassign', [AssetController::class, 'bulkUnassign'])->middleware('simple.permission:hr_assets');
    Route::post('/assets/bulk-status', [AssetController::class, 'bulkStatus'])->middleware('simple.permission:hr_assets');
});