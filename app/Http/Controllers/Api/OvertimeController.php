<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OvertimeService;
use App\Services\SimplePermissionService;
use App\Services\OvertimeCalculationService;
use App\DTOs\Overtime\OvertimeRequestFilterDTO;
use App\DTOs\Overtime\CreateOvertimeRequestDTO;
use App\DTOs\Overtime\UpdateOvertimeRequestDTO;
use App\Http\Requests\Overtime\CreateOvertimeRequestRequest;
use App\Http\Requests\Overtime\UpdateOvertimeRequestRequest;
use App\Http\Requests\Overtime\ApproveOvertimeRequestRequest;
use App\Http\Requests\Overtime\RejectOvertimeRequestRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OvertimeController extends Controller
{
    public function __construct(
        private readonly OvertimeService $overtimeService,
        private readonly SimplePermissionService $permissionService,
        private readonly OvertimeCalculationService $calculationService
    ) {}

    /**
     * Get overtime requests list.
     * GET /api/overtime/requests
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        try {
            // Check permission
            $hasPermission = $this->permissionService->checkPermission($user, 'overtime_req1');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض طلبات العمل الإضافي'
                ], 403);
            }

            $filters = OvertimeRequestFilterDTO::fromRequest($request->all());
            $result = $this->overtimeService->getPaginatedRequests($filters, $user);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to'],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('OvertimeController::index failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب طلبات العمل الإضافي',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show specific overtime request.
     * GET /api/overtime/requests/{id}
     */
    public function show(int $id)
    {
        $user = Auth::user();
        
        try {
            $hasPermission = $this->permissionService->checkPermission($user, 'overtime_req1');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض طلبات العمل الإضافي'
                ], 403);
            }

            $request = $this->overtimeService->getRequest($id, $user);

            return response()->json([
                'success' => true,
                'data' => $request->toArray()
            ]);
        } catch (\Exception $e) {
            Log::error('OvertimeController::show failed', [
                'error' => $e->getMessage(),
                'request_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getMessage() === 'الطلب غير موجود' ? 404 : 500);
        }
    }

    /**
     * Create new overtime request.
     * POST /api/overtime/requests
     */
    public function store(CreateOvertimeRequestRequest $request)
    {
        $user = Auth::user();
        
        try {
            $hasPermission = $this->permissionService->checkPermission($user, 'overtime_req2');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنشاء طلبات عمل إضافي'
                ], 403);
            }

            $validated = $request->validated();
            
            // Determine employee ID
            $userType = strtolower(trim($user->user_type ?? ''));
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            
            if ($userType === 'company' && isset($validated['employee_id'])) {
                // Company creating on behalf of employee
                $staffId = $validated['employee_id'];
                $companyId = $effectiveCompanyId;
            } else {
                // Employee creating for themselves
                $staffId = $user->user_id;
                $companyId = $effectiveCompanyId;
            }

            // Convert 12-hour time to 24-hour with date
            $clockIn24 = $this->calculationService->convertTo24Hour(
                $validated['clock_in'],
                $validated['request_date']
            );
            $clockOut24 = $this->calculationService->convertTo24Hour(
                $validated['clock_out'],
                $validated['request_date']
            );

            // Calculate request month
            $requestMonth = $this->calculationService->calculateRequestMonth($validated['request_date']);

            $dto = new CreateOvertimeRequestDTO(
                companyId: $companyId,
                staffId: $staffId,
                requestDate: $validated['request_date'],
                requestMonth: $requestMonth,
                clockIn: $clockIn24,
                clockOut: $clockOut24,
                overtimeReason: $validated['overtime_reason'],
                additionalWorkHours: $validated['additional_work_hours'] ?? 0,
                compensationType: $validated['compensation_type'],
                requestReason: $validated['request_reason'] ?? null
            );

            $result = $this->overtimeService->createRequest($dto, $user);

            Log::info('OvertimeController::store success', [
                'request_id' => $result->timeRequestId,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب العمل الإضافي بنجاح',
                'data' => $result->toArray()
            ], 201);
        } catch (\Exception $e) {
            Log::error('OvertimeController::store failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Update overtime request.
     * PUT /api/overtime/requests/{id}
     */
    public function update(UpdateOvertimeRequestRequest $request, int $id)
    {
        $user = Auth::user();
        
        try {
            $validated = $request->validated();

            $dto = UpdateOvertimeRequestDTO::fromRequest($validated);
            $result = $this->overtimeService->updateRequest($id, $dto, $user);

            Log::info('OvertimeController::update success', [
                'request_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث طلب العمل الإضافي بنجاح',
                'data' => $result->toArray()
            ]);
        } catch (\Exception $e) {
            Log::error('OvertimeController::update failed', [
                'error' => $e->getMessage(),
                'request_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Delete overtime request.
     * DELETE /api/overtime/requests/{id}
     */
    public function destroy(int $id)
    {
        $user = Auth::user();
        
        try {
            $this->overtimeService->deleteRequest($id, $user);

            Log::info('OvertimeController::destroy success', [
                'request_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف طلب العمل الإضافي بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('OvertimeController::destroy failed', [
                'error' => $e->getMessage(),
                'request_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get pending overtime requests awaiting approval.
     * GET /api/overtime/requests/pending
     */
    public function pending()
    {
        $user = Auth::user();
        
        try {
            $hasPermission = $this->permissionService->checkPermission($user, 'overtime_req3');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض الطلبات المعلقة'
                ], 403);
            }

            $requests = $this->overtimeService->getRequestsForApproval($user);

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);
        } catch (\Exception $e) {
            Log::error('OvertimeController::pending failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب الطلبات المعلقة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get team overtime requests.
     * GET /api/overtime/requests/team
     */
    public function team()
    {
        $user = Auth::user();
        
        try {
            $hasPermission = $this->permissionService->checkPermission($user, 'overtime_req1');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض طلبات الفريق'
                ], 403);
            }

            $requests = $this->overtimeService->getTeamRequests($user);

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);
        } catch (\Exception $e) {
            Log::error('OvertimeController::team failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب طلبات الفريق',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve overtime request.
     * POST /api/overtime/requests/{id}/approve
     */
    public function approve(ApproveOvertimeRequestRequest $request, int $id)
    {
        $user = Auth::user();
        
        try {
            $hasPermission = $this->permissionService->checkPermission($user, 'overtime_req3');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بالموافقة على طلبات العمل الإضافي'
                ], 403);
            }

            $validated = $request->validated();
            $result = $this->overtimeService->approveRequest(
                $id,
                $user,
                $validated['remarks'] ?? null
            );

            Log::info('OvertimeController::approve success', [
                'request_id' => $id,
                'approver_id' => $user->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تمت الموافقة على طلب العمل الإضافي بنجاح',
                'data' => $result->toArray()
            ]);
        } catch (\Exception $e) {
            Log::error('OvertimeController::approve failed', [
                'error' => $e->getMessage(),
                'request_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Reject overtime request.
     * POST /api/overtime/requests/{id}/reject
     */
    public function reject(RejectOvertimeRequestRequest $request, int $id)
    {
        $user = Auth::user();
        
        try {
            $hasPermission = $this->permissionService->checkPermission($user, 'overtime_req3');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك برفض طلبات العمل الإضافي'
                ], 403);
            }

            $validated = $request->validated();
            $result = $this->overtimeService->rejectRequest(
                $id,
                $user,
                $validated['reason']
            );

            Log::info('OvertimeController::reject success', [
                'request_id' => $id,
                'rejector_id' => $user->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم رفض طلب العمل الإضافي',
                'data' => $result->toArray()
            ]);
        } catch (\Exception $e) {
            Log::error('OvertimeController::reject failed', [
                'error' => $e->getMessage(),
                'request_id' => $id,
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get overtime statistics.
     * GET /api/overtime/stats
     */
    public function stats(Request $request)
    {
        $user = Auth::user();
        
        try {
            $hasPermission = $this->permissionService->checkPermission($user, 'overtime_req1');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض إحصائيات العمل الإضافي'
                ], 403);
            }

            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            $stats = $this->overtimeService->getStats($user, $fromDate, $toDate);

            return response()->json([
                'success' => true,
                'data' => $stats->toArray()
            ]);
        } catch (\Exception $e) {
            Log::error('OvertimeController::stats failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب الإحصائيات',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

