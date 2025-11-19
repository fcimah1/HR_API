<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveService;
use App\Models\User;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\Http\Requests\Leave\ApproveLeaveApplicationRequest;
use App\Http\Requests\Leave\CheckLeaveBalanceRequest;
use App\Http\Requests\Leave\CreateLeaveApplicationRequest;
use App\Http\Requests\Leave\UpdateLeaveApplicationRequest;
use App\Http\Requests\Leave\CreateLeaveTypeRequest;
use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Leave Management",
 *     description="Leave applications management"
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
    public function getLeaveTypes(Request $request)
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

            // الحصول على معرف الشركة الفعلي من الـ middleware SimpleCompanyIsolation
            $effectiveCompanyId = $request->attributes->get('effective_company_id') ?? $user->company_id;
            Log::info('LeaveController::start', [
                'success' => true,
                'effectiveCompanyId' => $effectiveCompanyId,
                'created_by' => $user->full_name
            ]);
            // Get leave types for the user's company and general types
            $leaveTypes = $this->leaveService->getActiveLeaveTypes($effectiveCompanyId);
            Log::info('LeaveController::getLeaveTypes', [
                'success' => true,
                'leaveTypes' => $leaveTypes,
                'effectiveCompanyId' => $effectiveCompanyId,
                'created_by' => $user->full_name
            ]);
            // Transform data to match expected format

            return response()->json([
                'success' => true,
                'data' => $leaveTypes,
                'effectiveCompanyId' => $effectiveCompanyId,
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
     *           
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
     * @OA\Get(
     *     path="/api/leaves/check-balance",
     *     summary="Check leave balance for an employee",
     *     description="Check if an employee has sufficient leave balance for the requested dates",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *    
     *     @OA\Parameter(
     *         name="leave_type_id",
     *         in="query",
     *         required=false,
     *         description="ID of the leave type",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         required=false,
     *         description="معرف الموظف المستهدف (للشركة/المدير لعرض رصيد موظف آخر)",
     *         @OA\Schema(type="integer")
     *     ),
     *  
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
    public function checkLeaveBalance(CheckLeaveBalanceRequest $request)
    {
        try {
            $user = Auth::user();
            $leaveTypeId = $request->input('leave_type_id');
            $requestedEmployeeId = $request->input('employee_id');

            // تحديد الموظف المستهدف: افتراضيًا المستخدم الحالي
            $targetEmployee = $user;

            if (!is_null($requestedEmployeeId)) {
                $requestedEmployeeId = (int) $requestedEmployeeId;

                // إذا كان يطلب موظفًا غير نفسه، نتحقق من الصلاحيات
                if ($requestedEmployeeId !== $user->user_id) {
                    $targetEmployee = User::find($requestedEmployeeId);

                    if (!$targetEmployee) {
                        return response()->json([
                            'success' => false,
                            'message' => 'الموظف المطلوب غير موجود',
                        ], 404);
                    }

                    if (!$this->permissionService->canAccessEmployee($user, $targetEmployee)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'ليس لديك صلاحية لعرض ملخص رصيد هذا الموظف',
                        ], 403);
                    }
                }
            }

            // الحصول على معرف الشركة الفعلي للموظف المستهدف
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($targetEmployee);

            $summary = $this->leaveService->getDetailedLeaveSummary(
                $targetEmployee->user_id,
                $effectiveCompanyId,
                $leaveTypeId !== null ? (int) $leaveTypeId : null
            );
            Log::info('LeaveController::checkLeaveBalance:summary', [
                'success' => true,
                'data' => $summary,
                'message' => $leaveTypeId === null
                    ? 'تم جلب ملخص رصيد جميع أنواع الإجازات بنجاح'
                    : 'تم جلب ملخص رصيد الإجازة لهذا النوع بنجاح',
            ]);

            return response()->json([
                'success' => true,
                'data' => $summary,
                'message' => $leaveTypeId === null
                    ? 'تم جلب ملخص رصيد جميع أنواع الإجازات بنجاح'
                    : 'تم جلب ملخص رصيد الإجازة لهذا النوع بنجاح',
            ]);

        } catch (\Exception $e) {
            Log::error('LeaveController::checkLeaveBalance failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب رصيد الإجازات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}