<?php

declare(strict_types=1);

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

/**
 * @OA\Tag(
 *     name="Overtime Management",
 *     description="Overtime requests management"
 * )
 */
class OvertimeController extends Controller
{
    public function __construct(
        private readonly OvertimeService $overtimeService,
        private readonly SimplePermissionService $permissionService,
        private readonly OvertimeCalculationService $calculationService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/overtime/requests",
     *     summary="Get overtime requests list",
     *     tags={"Overtime Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (pending/approved/rejected)",
     *         @OA\Schema(type="string", enum={"pending", "approved", "rejected"})
     *     ),
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Filter by employee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter from date (Y-m-d)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter to date (Y-m-d)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by employee name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Overtime requests retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب طلبات العمل الإضافي بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="time_request_id", type="integer", example=1205),
     *                     @OA\Property(property="company_id", type="integer", example=36),
     *                     @OA\Property(property="staff_id", type="integer", example=37),
     *                     @OA\Property(property="request_date", type="string", format="date", example="2025-11-25"),
     *                     @OA\Property(property="request_month", type="string", example="2025-11"),
     *                     @OA\Property(property="clock_in", type="string", example="2025-11-25 14:30:00"),
     *                     @OA\Property(property="clock_out", type="string", example="2025-11-25 19:00:00"),
     *                     @OA\Property(property="overtime_reason", type="integer", example=1),
     *                     @OA\Property(property="additional_work_hours", type="integer", example=0),
     *                     @OA\Property(property="compensation_type", type="integer", example=1),
     *                     @OA\Property(property="request_reason", type="string", nullable=true, example="عمل إضافي لإنهاء المشروع"),
     *                     @OA\Property(property="straight", type="number", format="float", example=1.5),
     *                     @OA\Property(property="time_a_half", type="number", format="float", example=2.0),
     *                     @OA\Property(property="double_overtime", type="number", format="float", example=1.0),
     *                     @OA\Property(property="total_hours", type="string", example="04:30"),
     *                     @OA\Property(property="compensation_banked", type="number", format="float", example=2.25),
     *                     @OA\Property(property="is_approved", type="integer", example=0, description="0=pending,1=approved,2=rejected"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-25 20:10:00"),
     *                     @OA\Property(
     *                         property="employee",
     *                         type="object",
     *                         @OA\Property(property="user_id", type="integer", example=37),
     *                         @OA\Property(property="full_name", type="string", example="محمد أحمد"),
     *                         @OA\Property(property="email", type="string", example="m.ahmed@example.com")
     *                     ),
     *                     @OA\Property(
     *                         property="approvals",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="status", type="integer", example=1),
     *                             @OA\Property(property="approval_level", type="integer", example=1),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-25 21:00:00"),
     *                             @OA\Property(
     *                                 property="staff",
     *                                 type="object",
     *                                 @OA\Property(property="user_id", type="integer", example=55),
     *                                 @OA\Property(property="full_name", type="string", example="مدير القسم")
     *                             )
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=42),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="غير مصرح - يجب تسجيل الدخول")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - ليس لديك صلاحية",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض طلبات العمل الإضافي")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="خطأ في التحقق من البيانات",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ في الخادم")
     *         )
     *     )
     * )
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
     * @OA\Get(
     *     path="/api/overtime/requests/{id}",
     *     summary="Show specific overtime request",
     *     tags={"Overtime Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Overtime request ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Overtime request details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="time_request_id", type="integer"),
     *                 @OA\Property(property="company_id", type="integer"),
     *                 @OA\Property(property="staff_id", type="integer"),
     *                 @OA\Property(property="employee_name", type="string"),
     *                 @OA\Property(property="request_date", type="string", format="date"),
     *                 @OA\Property(property="clock_in", type="string"),
     *                 @OA\Property(property="clock_out", type="string"),
     *                 @OA\Property(property="overtime_reason", type="string", example="STANDBY_PAY"),
     *                 @OA\Property(property="overtime_reason_label", type="string", example="Standby Pay"),
     *                 @OA\Property(property="compensation_type", type="string", example="BANKED"),
     *                 @OA\Property(property="compensation_type_label", type="string", example="Banked"),
     *                 @OA\Property(property="request_reason", type="string"),
     *                 @OA\Property(property="status", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Request not found")
     * )
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
     * @OA\Post(
     *     path="/api/overtime/requests",
     *     summary="Create new overtime request",
     *     tags={"Overtime Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"request_date", "clock_in", "clock_out", "overtime_reason", "compensation_type"},
     *             @OA\Property(property="employee_id", type="integer", example=37, description="معرف الموظف (للشركة/HR فقط عند الإنشاء نيابة عن موظف)"),
     *             @OA\Property(property="request_date", type="string", format="date", example="2025-11-25", description="تاريخ الطلب (يجب أن يكون بصيغة Y-m-d)"),
     *             @OA\Property(property="clock_in", type="string", example="2:30 PM", description="وقت البداية (صيغة 12 ساعة مع AM/PM)"),
     *             @OA\Property(property="clock_out", type="string", example="7:00 PM", description="وقت النهاية (صيغة 12 ساعة مع AM/PM)"),
     *             @OA\Property(
     *                 property="overtime_reason",
     *                 type="string",
     *                 example="STANDBY_PAY",
     *                 enum={"STANDBY_PAY", "WORK_THROUGH_LUNCH", "OUT_OF_TOWN", "SALARIED_EMPLOYEE", "ADDITIONAL_WORK_HOURS"},
     *                 description="سبب العمل الإضافي: STANDBY_PAY=انتظار, WORK_THROUGH_LUNCH=عمل خلال الغداء, OUT_OF_TOWN=خارج المدينة, SALARIED_EMPLOYEE=موظف براتب, ADDITIONAL_WORK_HOURS=ساعات إضافية"
     *             ),
     *             @OA\Property(
     *                 property="additional_work_hours",
     *                 type="integer",
     *                 example=0,
     *                 description="نوع ساعات العمل الإضافية (0-3). مطلوب عند اختيار overtime_reason=ADDITIONAL_WORK_HOURS"
     *             ),
     *             @OA\Property(
     *                 property="compensation_type",
     *                 type="string",
     *                 example="BANKED",
     *                 enum={"BANKED", "PAYOUT"},
     *                 description="نوع التعويض: BANKED=مدخر, PAYOUT=صرف مالي"
     *             ),
     *             @OA\Property(property="request_reason", type="string", example="عمل إضافي لإنهاء المشروع", description="سبب الطلب (اختياري، حد أقصى 1000 حرف)"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Overtime request created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء طلب العمل الإضافي بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بإنشاء طلبات عمل إضافي")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في إنشاء طلب العمل الإضافي")
     *         )
     *     )
     * )
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
            
            if (($userType === 'company' || $userType === 'staff') && isset($validated['employee_id'])) {
                // Company or staff creating on behalf of employee (with hierarchy validation)
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

            // Prepare data for DTO with converted times
            $dtoData = array_merge($validated, [
                'company_id' => $companyId,
                'staff_id' => $staffId,
                'request_month' => $requestMonth,
                'clock_in' => $clockIn24,
                'clock_out' => $clockOut24,
            ]);

            $dto = CreateOvertimeRequestDTO::fromRequest($dtoData);

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
     * @OA\Put(
     *     path="/api/overtime/requests/{id}",
     *     summary="Update overtime request",
     *     tags={"Overtime Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Overtime request ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"request_date", "clock_in", "clock_out", "overtime_reason", "compensation_type"},
     *             @OA\Property(property="request_date", type="string", format="date", example="2025-11-25"),
     *             @OA\Property(property="clock_in", type="string", example="2:30 PM"),
     *             @OA\Property(property="clock_out", type="string", example="7:00 PM"),
     *             @OA\Property(property="overtime_reason", type="string", example="STANDBY_PAY", enum={"STANDBY_PAY", "WORK_THROUGH_LUNCH", "OUT_OF_TOWN", "SALARIED_EMPLOYEE", "ADDITIONAL_WORK_HOURS"}),
     *             @OA\Property(property="additional_work_hours", type="integer", example=0),
     *             @OA\Property(property="compensation_type", type="string", example="BANKED", enum={"BANKED", "PAYOUT"}),
     *             @OA\Property(property="request_reason", type="string", example="تحديث السبب")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Overtime request updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث طلب العمل الإضافي بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Request not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateOvertimeRequestRequest $request, int $id)
    {
        $user = Auth::user();
        
        try {
            $validated = $request->validated();

            // Use DTO fromRequest method to handle string enum conversion
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
     * @OA\Delete(
     *     path="/api/overtime/requests/{id}",
     *     summary="Delete overtime request",
     *     tags={"Overtime Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Overtime request ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Overtime request deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف طلب العمل الإضافي بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Request not found"),
     *     @OA\Response(response=422, description="Cannot delete approved/rejected requests")
     * )
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
     * @OA\Get(
     *     path="/api/overtime/requests/pending",
     *     summary="Get pending overtime requests awaiting approval",
     *     description="Returns all pending overtime requests that require approval by the authenticated manager/HR",
     *     tags={"Overtime Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Pending requests retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="time_request_id", type="integer"),
     *                 @OA\Property(property="employee_name", type="string"),
     *                 @OA\Property(property="request_date", type="string", format="date"),
     *                 @OA\Property(property="clock_in", type="string"),
     *                 @OA\Property(property="clock_out", type="string"),
     *                 @OA\Property(property="status", type="string", example="pending")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized - Manager/HR role required")
     * )
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
     * @OA\Get(
     *     path="/api/overtime/requests/team",
     *     summary="Get team overtime requests",
     *     description="Returns overtime requests for all team members",
     *     tags={"Overtime Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Team requests retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="time_request_id", type="integer"),
     *                 @OA\Property(property="employee_name", type="string"),
     *                 @OA\Property(property="request_date", type="string", format="date"),
     *                 @OA\Property(property="clock_in", type="string"),
     *                 @OA\Property(property="clock_out", type="string"),
     *                 @OA\Property(property="status", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
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
     * @OA\Post(
     *     path="/api/overtime/requests/{id}/approve",
     *     summary="Approve overtime request",
     *     tags={"Overtime Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Overtime request ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="remarks", type="string", example="موافق عليه", description="ملاحظات الموافقة (اختياري، حد أقصى 500 حرف)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Overtime request approved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تمت الموافقة على طلب العمل الإضافي بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized - Manager/HR role required"),
     *     @OA\Response(response=404, description="Request not found"),
     *     @OA\Response(response=422, description="Request already processed")
     * )
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
     * @OA\Post(
     *     path="/api/overtime/requests/{id}/reject",
     *     summary="Reject overtime request",
     *     tags={"Overtime Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Overtime request ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="لا يوجد ضغط عمل كافي", description="سبب الرفض (مطلوب، حد أقصى 500 حرف)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Overtime request rejected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم رفض طلب العمل الإضافي"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized - Manager/HR role required"),
     *     @OA\Response(response=404, description="Request not found"),
     *     @OA\Response(response=422, description="Validation error or request already processed")
     * )
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

    // /**
    //  * @OA\Get(
    //  *     path="/api/overtime/stats",
    //  *     summary="Get overtime statistics",
    //  *     description="Returns overtime statistics including total hours, approved/rejected counts",
    //  *     tags={"Overtime Management"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="from_date",
    //  *         in="query",
    //  *         description="Start date for statistics (Y-m-d)",
    //  *         @OA\Schema(type="string", format="date", example="2025-01-01")
    //  *     ),
    //  *     @OA\Parameter(
    //  *         name="to_date",
    //  *         in="query",
    //  *         description="End date for statistics (Y-m-d)",
    //  *         @OA\Schema(type="string", format="date", example="2025-12-31")
    //  *     ),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Statistics retrieved successfully",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="data", type="object",
    //  *                 @OA\Property(property="total_requests", type="integer"),
    //  *                 @OA\Property(property="approved_requests", type="integer"),
    //  *                 @OA\Property(property="rejected_requests", type="integer"),
    //  *                 @OA\Property(property="pending_requests", type="integer"),
    //  *                 @OA\Property(property="total_hours", type="number"),
    //  *                 @OA\Property(property="approved_hours", type="number")
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=403, description="Unauthorized - Manager/HR role required")
    //  * )
    //  */
    // public function stats(Request $request)
    // {
    //     $user = Auth::user();
        
    //     try {
    //         $hasPermission = $this->permissionService->checkPermission($user, 'overtime_req1');
    //         if (!$hasPermission) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'غير مصرح لك بعرض إحصائيات العمل الإضافي'
    //             ], 403);
    //         }

    //         $fromDate = $request->get('from_date');
    //         $toDate = $request->get('to_date');

    //         $stats = $this->overtimeService->getStats($user, $fromDate, $toDate);

    //         return response()->json([
    //             'success' => true,
    //             'data' => $stats->toArray()
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('OvertimeController::stats failed', [
    //             'error' => $e->getMessage(),
    //             'user_id' => $user->user_id
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'فشل في جلب الإحصائيات',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * @OA\Get(
     *     path="/api/overtime/enums",
     *     summary="Get overtime enums",
     *     description="Returns enums for overtime requests including travel modes and reasons",
     *     tags={"Overtime Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Enums retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="travel_modes", type="array",
     *                     @OA\Items(type="string")
     *                 ),
     *                 @OA\Property(property="reasons", type="array",
     *                     @OA\Items(type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getEnums()
    {
        try {
            $enums = $this->overtimeService->getOvertimeEnums();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قوائم حالات العمل الإضافي بنجاح',
                'data' => $enums
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب حالات القوائم',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

