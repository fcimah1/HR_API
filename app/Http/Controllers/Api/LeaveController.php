<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Leave\CheckLeaveBalanceDTO as LeaveCheckLeaveBalanceDTO;
use App\DTOs\Leave\CreateLeaveSettlementDTO;
use App\Http\Controllers\Controller;
use App\Services\LeaveService;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\DTOs\Leave\LeaveAdjustmentFilterDTO;
use App\DTOs\Leave\CreateLeaveAdjustmentDTO;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\DTOs\Leave\UpdateLeaveAdjustmentDTO;
use App\Http\Requests\Leave\ApproveLeaveApplicationRequest;
use App\Http\Requests\Leave\CheckLeaveBalanceRequest;
use App\Http\Requests\Leave\CreateLeaveApplicationRequest;
use App\Http\Requests\Leave\CreateLeaveSettlementRequest;
use App\Http\Requests\Leave\UpdateLeaveApplicationRequest;
use App\Http\Requests\Leave\CreateLeaveAdjustmentRequest;
use App\Http\Requests\Leave\CreateLeaveTypeRequest;
use App\Http\Requests\Leave\UpdateLeaveAdjustmentRequest;

use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Leave Management",
 *     description="Leave applications and adjustments management"
 * )
 */
class LeaveController extends Controller
{
    public $simplePermissionService;
    public function __construct(
        private readonly LeaveService $leaveService,
        private readonly SimplePermissionService $permissionService
    ) {
        $this->simplePermissionService = $permissionService;
    }
    /**
     * @OA\Get(
     *     path="/api/leaves/applications",
     *     summary="Get leave applications",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Filter by employee ID (managers/HR only)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (true=approved, false=pending)",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="leave_type_id",
     *         in="query",
     *         description="Filter by leave type ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave applications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="leave_id", type="integer"),
     *                 @OA\Property(property="employee_name", type="string"),
     *                 @OA\Property(property="leave_type_name", type="string"),
     *                 @OA\Property(property="from_date", type="string", format="date"),
     *                 @OA\Property(property="to_date", type="string", format="date"),
     *                 @OA\Property(property="duration_days", type="integer"),
     *                 @OA\Property(property="reason", type="string"),
     *                 @OA\Property(property="status_text", type="string")
     *             )),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     )
     * )
     */
    public function getApplications(Request $request)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave1');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض طلبات الإجازات'
                ], 403);
            }
            $filters = LeaveApplicationFilterDTO::fromRequest($request->all());
            $result = $this->leaveService->getPaginatedApplications($filters,$user);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب طلبات الإجازات بنجاح',
                'created by' => $user->full_name,
                ...$result
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::getApplications failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leaves/applications",
     *     summary="Create a new leave application",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_id","from_date","to_date","reason"},
     *             @OA\Property(property="leave_type_id", type="integer", example=323, description="معرف نوع الإجازة - استخدم 311-316 أو 194,195,199,320-323"),
     *             @OA\Property(property="from_date", type="string", format="date", example="2025-12-01", description="تاريخ بداية الإجازة"),
     *             @OA\Property(property="to_date", type="string", format="date", example="2025-12-07", description="تاريخ نهاية الإجازة"),
     *             @OA\Property(property="reason", type="string", example="إجازة سنوية للراحة والاستجمام", description="سبب الإجازة (10 أحرف على الأقل)"),
     *             @OA\Property(property="duty_employee_id", type="integer", example=37, description="معرف الموظف البديل (اختياري) - يجب أن يكون من نفس الشركة: 36,37,118,702,703,725,726,744"),
     *             @OA\Property(property="is_half_day", type="boolean", example=false, description="هل الإجازة نصف يوم؟"),
     *             @OA\Property(property="leave_hours", type="string", example="8", description="عدد ساعات الإجازة"),
     *             @OA\Property(property="remarks", type="string", example="ملاحظات إضافية", description="ملاحظات (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave application created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء طلب الإجازة بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="leave_type_id", type="array", @OA\Items(type="string", example="نوع الإجازة غير متاح لشركتك")),
     *                 @OA\Property(property="duty_employee_id", type="array", @OA\Items(type="string", example="الموظف البديل يجب أن يكون من نفس الشركة ونشط")),
     *                 @OA\Property(property="reason", type="array", @OA\Items(type="string", example="سبب الإجازة يجب أن يكون 10 أحرف على الأقل"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function createApplication(CreateLeaveApplicationRequest $request)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave3');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنشاء طلبات الإجازات'
                ], 403);
            }
            // الحصول على معرف الشركة الفعلي من attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id');
            
            $dto = CreateLeaveApplicationDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $user->user_id
            );

            $application = $this->leaveService->createApplication($dto);

            Log::info('LeaveController::createApplication', [
                'success' => true,
                'created by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب الإجازة بنجاح',
                'data' => $application
            ], 201);

        } catch (\Exception $e) {
            Log::error('LeaveController::createApplication failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء طلب الإجازة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leaves/applications/{id}",
     *     summary="Get a specific leave application",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave application not found"
     *     )
     * )
     */
    public function showApplication(int $id, Request $request)
    {
        try {
            $user = Auth::user();
            // الحصول على معرف الشركة الفعلي من attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id');
            
            if (in_array($user->user_type, ['company', 'admin', 'hr', 'manager'])) {
                $application = $this->leaveService->getApplicationById($id, $effectiveCompanyId);
            } else {
                $application = $this->leaveService->getApplicationById($id, null, $user->user_id);
            }

            if (!$application) {
                Log::info('LeaveController::showApplication', [
                    'success' => false,
                    'created by' => $user->full_name
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود'
                ], 404);
            }

            Log::info('LeaveController::showApplication', [
                'success' => true,
                'created by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'data' => $application
            ]);

        } catch (\Exception $e) {
            Log::error('LeaveController::showApplication failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب طلب الإجازة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/leaves/applications/{id}",
     *     summary="Update a leave application",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="from_date", type="string", format="date"),
     *             @OA\Property(property="to_date", type="string", format="date"),
     *             @OA\Property(property="reason", type="string"),
     *             @OA\Property(property="remarks", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application updated successfully"
     *     )
     * )
     */
    public function updateApplication(UpdateLeaveApplicationRequest $request, int $id)
    {
        
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave4');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل طلبات الإجازات'
                ], 403);
            }
            $dto = UpdateLeaveApplicationDTO::fromRequest($request->validated());
            Log::info('LeaveController::updateApplication', [
                'success' => true,
                'dto' => $dto,
                'created by' => $user->full_name
            ]);
            $application = $this->leaveService->update_Application($id, $dto, $user);

            if (!$application) {
                Log::info('LeaveController::updateApplication', [
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود أو غير مصرح لك بتعديله',

                    'created by' => $user->full_name
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود أو غير مصرح لك بتعديله'
                ], 404);
            }

            Log::info('LeaveController::updateApplication', [
                'success' => true,
                'created by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث طلب الإجازة بنجاح',
                'data' => $application
            ]);

        } catch (\Exception $e) {
            Log::error('LeaveController::updateApplication failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/leaves/applications/{id}/cancel",
     *     summary="Cancel a leave application (mark as rejected)",
     *     description="Cancels a leave application by marking it as rejected. The application remains in the database for audit purposes with status 'rejected' and remarks indicating it was cancelled by the employee.",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Leave application ID to cancel",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إلغاء طلب الإجازة بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave application not found or cannot be cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب الإجازة غير موجود أو لا يمكن إلغاؤه")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot cancel processed application",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="لا يمكن إلغاء طلب تم الموافقة عليه مسبقاً")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing token"
     *     )
     * )
     */
    public function cancelApplication(int $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave6');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإلغاء طلبات الإجازات'
                ], 403);
            }
            $success = $this->leaveService->cancelApplication($id, $user);

            if (!$success) {
                Log::info('LeaveController::cancelApplication', [
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود أو لا يمكن إلغاؤه',
                    'create_by' => $user->full_name
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الإجازة غير موجود أو لا يمكن إلغاؤه'
                ], 404);
            }

            Log::info('LeaveController::cancelApplication', [
                'success' => true,
                'message' => 'تم إلغاء طلب الإجازة بنجاح',
                'create_by' => $user->full_name
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء طلب الإجازة بنجاح',
                'create_by' => $user->full_name
            ]);

        } catch (\Exception $e) {
            Log::info('LeaveController::cancelApplication', [
                'success' => false,
                'message' => $e->getMessage(),
                'create_by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/leaves/adjustments",
     *     summary="Get leave adjustments",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustments retrieved successfully"
     *     )
     * )
     */
    public function getAdjustments(Request $request)
    {
        try {
        $user = Auth::user();
        $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment');
        if (!$isUserHasThisPermission) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بعرض تسويات الإجازات'
            ], 403);
        }
        $filters = LeaveAdjustmentFilterDTO::fromRequest($request->all(), $user);
        
        Log::info('LeaveController::getAdjustments filters', [
            'filters' => $filters,
            'user' => $user
        ]);
        $result = $this->leaveService->getPaginatedAdjustments($filters,$user);
        Log::info('LeaveController::getAdjustments result', [
            'success' => true,
            'message' => 'تم جلب تسويات الإجازات بنجاح',
            'result' => $result,
            'create_by' => $user->full_name 
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب تسويات الإجازات بنجاح',
            'create_by' => $user->full_name,
            'company_id' => $user->company_id,
            ...$result
        ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::getAdjustments', [
                'success' => false,
                'message' => $e->getMessage(),
                'create_by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leaves/adjustments",
     *     summary="Create a new leave adjustment",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_id","adjust_hours","reason_adjustment"},
     *             @OA\Property(property="leave_type_id", type="integer", example=1),
     *             @OA\Property(property="adjust_hours", type="string", example="8"),
     *             @OA\Property(property="reason_adjustment", type="string", example="تسوية إجازة متراكمة"),
     *             @OA\Property(property="adjustment_date", type="string", format="date", example="2025-11-15"),
     *             @OA\Property(property="duty_employee_id", type="integer", example=25)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave adjustment created successfully"
     *     )
     * )
     */
    public function createAdjustment(CreateLeaveAdjustmentRequest $request)
    {
        
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment1');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنشاء تسويات الإجازات'
                ], 403);
            }
            // الحصول على معرف الشركة الفعلي من attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id');

            $dto = CreateLeaveAdjustmentDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $user->user_id
            );

            Log::info('LeaveService::createAdjustment started', [
                'dto' => $dto->toArray(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId
            ]);

            $adjustment = $this->leaveService->createAdjust($dto);

            Log::info('LeaveService::createAdjustment success', [
                'adjustment' => $adjustment,
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId,    
                'created_by' => $user->full_name
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب التسوية بنجاح',
                'created by' => $user->full_name,
                'data' => $adjustment
            ], 201);

        } catch (\Exception $e) {
            Log::error('LeaveController::createAdjustment failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId,
                'created_by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء طلب التسوية',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leaves/types",
     *     summary="Get available leave types",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Leave types retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="leave_type_id", type="integer", example=1),
     *                 @OA\Property(property="leave_type_name", type="string", example="إجازة سنوية"),
     *                 @OA\Property(property="leave_type_short_name", type="string", example="سنوية"),
     *                 @OA\Property(property="leave_days", type="integer", example=30),
     *                 @OA\Property(property="leave_type_status", type="boolean", example=true)
     *             ))
     *         )
     *     )
     * )
     */
    public function getLeaveTypes()
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_type1');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض أنواع الإجازات'
                ], 403);
            }
        
            // Get leave types for the user's company and general types
            $leaveTypes = $this->leaveService->getActiveLeaveTypes($user->company_id);
            Log::info('LeaveController::getLeaveTypes', [
                'success' => true,
                'leaveTypes' => $leaveTypes,
                'created_by' => $user->full_name
            ]);
            // Transform data to match expected format

            return response()->json([
                'success' => true,
                'data' => $leaveTypes,
                'message' => 'تم جلب أنواع الإجازات بنجاح',
                'created_by' => $user->full_name,
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::getLeaveTypes failed', [
                'error' => $e->getMessage(),
                'created_by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب أنواع الإجازات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leaves/types",
     *     summary="Create a new leave type (HR/Admin only)",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_name","leave_days"},
     *             @OA\Property(property="leave_type_name", type="string", example="إجازة دراسية"),
     *             @OA\Property(property="leave_type_short_name", type="string", example="دراسية"),
     *             @OA\Property(property="leave_days", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave type created successfully"
     *     )
     * )
     */
    public function createLeaveType(CreateLeaveTypeRequest $request)
    {
        try {
            $user = Auth::user();
    
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_type2');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنشاء أنواع إجازات جديدة'
                ], 403);
            }
        
            $effectiveCompanyId = $request->attributes->get('effective_company_id');

            $dto = CreateLeaveTypeDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
            );

            Log::info('LeaveService::createType started', [
                'dto' => $dto->toArray(),
                'created by' => $user->full_name
            ]);

            $leaveType = $this->leaveService->createLeaveType($dto);

            Log::info('LeaveService::createType completed', [
                'dto' => $dto->toArray(),
                'leave_type' => $leaveType,
                'created by' => $user->full_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء نوع الإجازة بنجاح',
                'data' => $leaveType,
                'created by' => $user->full_name
            ], 201);
        } catch (\Exception $e) {
            Log::error('LeaveController::createLeaveType failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء نوع الإجازة',
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leaves/applications/{id}/approve",
     *     summary="Approve leave application (Managers/HR only)",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="remarks", type="string", example="موافق على الطلب")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave application approved successfully"
     *     )
     * )
     */
    public function approveApplication(ApproveLeaveApplicationRequest $request, int $id)
    {
        $user = Auth::user();
        try {
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave7');
        if (!$isUserHasThisPermission) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالموافقة على الطلبات'
            ], 403);
        }
        Log::info('LeaveController::Request received', [
            'request' => $request->all(),
            'application_id' => $id,
            'created by' => $user->full_name
        ]);
        // استدعاء خدمة الموافقة على الطلب
        $application = $this->leaveService->approveApplication($id, $request);

        // إذا رجع السيرفس Response جاهز (حالة خطأ)، نعيده كما هو
        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        // في حالة النجاح، يكون $application عبارة عن LeaveApplicationResponseDTO
        Log::info('LeaveController::Approved', [
            'success' => true,
            'application' => $application,
            'created by' => $user->full_name
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم الموافقة على طلب الإجازة بنجاح',
            'data' => $application
        ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::approveApplication failed', [
                'message' => 'فشل في الموافقة على طلب الإجازة',
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في الموافقة على طلب الإجازة',
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leaves/adjustments/{id}/approve",
     *     summary="Approve leave adjustment (Managers/HR only)",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustment approved successfully"
     *     )
     * )
     */
    public function approveAdjustment(int $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment4');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بالموافقة على الطلبات'
                ], 403);
            }

            // استخدام المعرف الفعلي للشركة لدعم مالك الشركة
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            Log::info('LeaveController::start', [
                'companyId' => $companyId,
                'created by' => $user->full_name
            ]);
            $adjustment = $this->leaveService->approveAdjustment(
                $id,
                $companyId,
                $user->user_id
            );

            Log::info('LeaveController::approveAdjustment', [
                'success' => true,
                'adjustment' => $adjustment,
                'created by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم الموافقة على طلب التسوية بنجاح',
                'data' => $adjustment,
                'created by' => $user->full_name
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::approveAdjustment failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في الموافقة على طلب التسوية',
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leaves/stats",
     *     summary="Get leave statistics (Managers/HR only)",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Leave statistics retrieved successfully"
     *     )
     * )
     */
    public function getStats()
    {
        $user = Auth::user();
        try {

            if (!in_array($user->user_type, ['company', 'admin', 'hr', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض الإحصائيات',
                    'created by' => $user->full_name
                ], 403);
            }
            // effective company id

            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $stats = $this->leaveService->getLeaveStatistics($companyId);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'created by' => $user->full_name
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveController::getStats failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في عرض الإحصائيات',
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/leaves/adjustments/{id}",
     *     summary="Update a leave adjustment",
     *     description="Updates a leave adjustment. Only pending adjustments can be updated.",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Leave adjustment ID to update",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="leave_type_id", type="integer", example=323),
     *             @OA\Property(property="adjust_hours", type="string", example="8"),
     *             @OA\Property(property="reason_adjustment", type="string", example="تحديث سبب التسوية"),
     *             @OA\Property(property="adjustment_date", type="string", format="date", example="2025-12-01"),
     *             @OA\Property(property="duty_employee_id", type="integer", example=37)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustment updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث تسوية الإجازة بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave adjustment not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="تسوية الإجازة غير موجودة أو غير مصرح لك بتعديلها")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot update processed adjustment",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="لا يمكن تعديل التسوية بعد المراجعة")
     *         )
     *     )
     * )
     */
    public function updateAdjustment(UpdateLeaveAdjustmentRequest $request, int $id)
    {
        
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment2');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل تسويات الإجازات'
                ], 403);
            }
            $dto = UpdateLeaveAdjustmentDTO::fromRequest($request->validated());
            $adjustment = $this->leaveService->updateAdjustment($id, $dto, $user->user_id);

            if (!$adjustment) {
                return response()->json([
                    'success' => false,
                    'message' => 'تسوية الإجازة غير موجودة أو غير مصرح لك بتعديلها',
                    'created by' => $user->full_name
                ], 404);
            }

            Log::info('LeaveController::updateAdjustment', [
                'success' => true,
                'adjustment' => $adjustment,
                'created by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث تسوية الإجازة بنجاح',
                'data' => $adjustment->toArray()
            ]);

        } catch (\Exception $e) {
            Log::error('LeaveController::updateAdjustment failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'created by' => $user->full_name
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/leaves/adjustments/{id}/cancel",
     *     summary="Cancel a leave adjustment (mark as rejected)",
     *     description="Cancels a leave adjustment by marking it as rejected. The adjustment remains in the database for audit purposes.",
     *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Leave adjustment ID to cancel",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustment cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إلغاء تسوية الإجازة بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave adjustment not found or cannot be cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="تسوية الإجازة غير موجودة أو لا يمكن إلغاؤها")
     *         )
     *     )
     * )
     */
    public function cancelAdjustment(int $id)
    {
        
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment3');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإلغاء تسويات الإجازات'
                ], 403);
            }
            $success = $this->leaveService->cancelAdjustment($id, $user->user_id);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'تسوية الإجازة غير موجودة أو لا يمكن إلغاؤها',
                    'created by' => $user->full_name
                ], 404);
            }

            Log::info('LeaveController::cancelAdjustment', [
                'success' => true,
                'message' => 'تم إلغاء تسوية الإجازة بنجاح',
                'created by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء تسوية الإجازة بنجاح'
            ]);

        } catch (\Exception $e) {
            Log::error('LeaveController::cancelAdjustment failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'created by' => $user->full_name
            ], 422);
        }
    }

    /**
 * @OA\Get(
 *     path="/leaves/check-balance",
 *     summary="Check leave balance for an employee",
 *     description="Check if an employee has sufficient leave balance for the requested dates",
 *     tags={"Leave Management"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="employee_id",
 *         in="query",
 *         required=true,
 *         description="ID of the employee",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="leave_type_id",
 *         in="query",
 *         required=true,
 *         description="ID of the leave type",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="start_date",
 *         in="query",
 *         required=true,
 *         description="Start date of the leave (Y-m-d)",
 *         @OA\Schema(type="string", format="date")
 *     ),
 *     @OA\Parameter(
 *         name="end_date",
 *         in="query",
 *         required=true,
 *         description="End date of the leave (Y-m-d)",
 *         @OA\Schema(type="string", format="date")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="has_sufficient_balance", type="boolean"),
 *                 @OA\Property(property="available_balance", type="number", format="float"),
 *                 @OA\Property(property="requested_days", type="number", format="float"),
 *                 @OA\Property(property="message", type="string")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Unauthenticated")
 *         )
 *     )
 * )
 */
    public function settleLeave(CreateLeaveSettlementRequest $request)
    {
        $user = Auth::user();

        try {
            // الحصول على معرف الشركة الفعلي من attributes
            $effectiveCompanyId = $request->attributes->get('effective_company_id') ?? $user->company_id;
            
            $dto = CreateLeaveSettlementDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $user->user_id
            );

            $result = $this->leaveService->handleLeaveSettlement($dto);

            Log::info('LeaveController::settleLeave', [
                'success' => true,
                'created by' => $user->full_name,
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('LeaveController::settleLeave failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422); // Use 422 for business logic errors like insufficient balance
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leaves/settlement",
     *     summary="Settle employee's accrued leave balance (Encashment or Take Leave)",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_id","hours_to_settle","settlement_type"},
     *             @OA\Property(property="leave_type_id", type="integer", example=323, description="معرف نوع الإجازة المراد تسويتها"),
     *             @OA\Property(property="hours_to_settle", type="number", format="float", example=40.5, description="عدد الساعات المراد تسويتها"),
     *             @OA\Property(property="settlement_type", type="string", example="encashment", enum={"encashment", "take_leave"}, description="نوع التسوية: صرف نقدي (encashment) أو أخذ إجازة (take_leave)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave settlement processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تمت تسوية 40.5 ساعة بنجاح كصرف نقدي. تم تحديث رصيد الإجازات."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="settlement_type", type="string"),
     *                 @OA\Property(property="hours_settled", type="number"),
     *                 @OA\Property(property="old_balance", type="number"),
     *                 @OA\Property(property="new_balance", type="number"),
     *                 @OA\Property(property="record", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation or insufficient balance error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="الرصيد المتاح (20.0 ساعة) غير كافٍ لتسوية 40.5 ساعة.")
     *         )
     *     )
     * )
     */
    public function checkLeaveBalance(CheckLeaveBalanceRequest $request)
    {
        try {
            // Create DTO from validated request
            $dto = new LeaveCheckLeaveBalanceDTO(
                employeeId: $request->input('employee_id'),
                leaveTypeId: $request->input('leave_type_id'),
                startDate: $request->input('start_date'),
                endDate: $request->input('end_date'),
                companyId: Auth::user()->company_id
            );

            // Get available balance
            $availableBalance = $this->leaveService->getAvailableLeaveBalance(
                $dto->employeeId,
                $dto->leaveTypeId,
                $dto->companyId
            );

            // Calculate requested days
            $requestedDays = $dto->getRequestedDays();
            $hasSufficientBalance = $availableBalance >= $requestedDays;

            return response()->json([
                'success' => true,
                'data' => [
                    'has_sufficient_balance' => $hasSufficientBalance,
                    'available_balance' => (float) $availableBalance,
                    'requested_days' => (float) $requestedDays,
                    'message' => $hasSufficientBalance 
                        ? 'Sufficient balance available' 
                        : 'Insufficient leave balance'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}