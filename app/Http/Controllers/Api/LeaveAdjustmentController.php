<?php

namespace App\Http\Controllers\Api;

use App\DTOs\LeaveAdjustment\CreateLeaveAdjustmentDTO;
use App\DTOs\LeaveAdjustment\LeaveAdjustmentFilterDTO;
use App\DTOs\LeaveAdjustment\UpdateLeaveAdjustmentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveAdjustment\CreateLeaveAdjustmentRequest;
use App\Http\Requests\LeaveAdjustment\UpdateLeaveAdjustmentRequest;
use App\Http\Requests\LeaveAdjustment\ApproveLeaveAdjustmentRequest;
use App\Models\User;
use App\Services\LeaveAdjustmentService;
use App\Services\LeaveService;
use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


/**
 * @OA\Tag(
 *     name="Leave Adjustments",
 *     description="Leave Adjustments management"
 * )
 */

class LeaveAdjustmentController extends Controller
{
    public $simplePermissionService;
    public function __construct(
        private readonly LeaveAdjustmentService $leaveAdjustmentService,
        private readonly SimplePermissionService $permissionService,
        private readonly LeaveService $leaveServices,
    ) {
        $this->simplePermissionService = $permissionService;
    }


    /**
     * @OA\Get(
     *     path="/api/leaves/adjustments",
     *     summary="Get leave adjustments",
     *     tags={"Leave Adjustments"},
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
     *         description="Filter by status (pending/approved/rejected)",
     *         @OA\Schema(type="string", enum={"pending", "approved", "rejected"})
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
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by employee name or leave type name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustments retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب تسويات الإجازات بنجاح"),
     *             @OA\Property(property="created by", type="string", example="محمد علي"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="adjustment_id", type="integer", example=501),
     *                     @OA\Property(property="company_id", type="integer", example=36),
     *                     @OA\Property(property="employee_id", type="integer", example=37),
     *                     @OA\Property(property="leave_type_id", type="integer", example=323),
     *                     @OA\Property(property="adjust_hours", type="number", format="float", example=8.0),
     *                     @OA\Property(property="reason_adjustment", type="string", example="تسوية رصيد سنوية"),
     *                     @OA\Property(property="status", type="integer", enum={0,1,2}, example=0),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-01 08:30:00"),
     *                     @OA\Property(property="adjustment_date", type="string", format="date", example="2025-12-01"),
     *                     @OA\Property(
     *                         property="employee",
     *                         type="object",
     *                         @OA\Property(property="user_id", type="integer", example=37),
     *                         @OA\Property(property="full_name", type="string", example="محمد أحمد"),
     *                         @OA\Property(property="email", type="string", example="m.ahmed@example.com")
     *                     ),
     *                     @OA\Property(
     *                         property="leaveType",
     *                         type="object",
     *                         @OA\Property(property="constants_id", type="integer", example=323),
     *                         @OA\Property(property="category_name", type="string", example="سنوية"),
     *                         @OA\Property(property="field_two", type="number", example=21, description="عدد الأيام")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=75),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="from", type="integer", example=1),
     *             @OA\Property(property="to", type="integer", example=15)
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
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض تسويات الإجازات")
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
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function getAdjustments(Request $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment');
            if (!$isUserHasThisPermission) {
                Log::info('LeaveAdjustmentController::getAdjustments', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'غير مصرح لك بعرض تسويات الإجازات'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض تسويات الإجازات'
                ], 403);
            }

            // التحقق من صلاحيات المستوى الهرمي عند طلب موظف آخر
            if ($request->has('employee_id') && $request->input('employee_id') != $user->user_id) {
                $targetEmployee = User::find($request->input('employee_id'));
                if (!$targetEmployee) {
                    Log::info('LeaveAdjustmentController::getAdjustments', [
                        'success' => false,
                        'user_id' => $user->user_id,
                        'company_id' => $effectiveCompanyId ?? null,
                        'message' => 'الموظف المطلوب غير موجود'
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'الموظف المطلوب غير موجود'
                    ], 404);
                }

                if (!$this->permissionService->canViewEmployeeRequests($user, $targetEmployee)) {
                    Log::info('LeaveAdjustmentController::getAdjustments', [
                        'success' => false,
                        'user_id' => $user->user_id,
                        'company_id' => $effectiveCompanyId ?? null,
                        'message' => 'غير مصرح لك بعرض تسويات هذا الموظف'
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'ليس لديك صلاحية لعرض تسويات هذا الموظف'
                    ], 403);
                }
            }

            $filters = LeaveAdjustmentFilterDTO::fromRequest($request->all());
            $result = $this->leaveAdjustmentService->getPaginatedAdjustments($filters, $user);

            Log::info('LeaveAdjustmentController::getAdjustments', [
                'success' => true,
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'message' => 'تم جلب تسويات الإجازات بنجاح'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم جلب تسويات الإجازات بنجاح',
                'created by' => $user->full_name,
                ...$result
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveAdjustmentController::getAdjustments', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'created_by' => $user->full_name
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
     *     tags={"Leave Adjustments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_id","adjust_hours","reason_adjustment"},
     *             @OA\Property(property="employee_id", type="integer", example=755),
     *             @OA\Property(property="leave_type_id", type="integer", example=1),
     *             @OA\Property(property="adjust_hours", type="string", example="8"),
     *             @OA\Property(property="reason_adjustment", type="string", example="تسوية إجازة متراكمة"),
     *             @OA\Property(property="adjustment_date", type="string", format="date", example="2025-11-15")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave adjustment created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء طلب التسوية بنجاح"),
     *             @OA\Property(property="created by", type="string"),
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
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بإنشاء تسويات الإجازات")
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
     *             @OA\Property(property="message", type="string", example="فشل في إنشاء طلب التسوية")
     *         )
     *     )
     * )
     */
    public function createAdjustment(CreateLeaveAdjustmentRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment1');
            if (!$isUserHasThisPermission) {
                Log::info('LeaveAdjustmentController::createAdjustment', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'غير مصرح لك بإنشاء تسويات الإجازات'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنشاء تسويات الإجازات'
                ], 403);
            }

            // التحقق من صلاحيات المستوى الهرمي عند إنشاء لموظف آخر
            $validated = $request->validated();
            if (isset($validated['employee_id']) && $validated['employee_id'] != $user->user_id) {
                $targetEmployee = User::find($validated['employee_id']);
                if (!$targetEmployee) {
                    Log::info('LeaveAdjustmentController::createAdjustment', [
                        'success' => false,
                        'user_id' => $user->user_id,
                        'company_id' => $effectiveCompanyId ?? null,
                        'message' => 'الموظف المطلوب غير موجود'
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'الموظف المطلوب غير موجود'
                    ], 404);
                }

                if (!$this->permissionService->canViewEmployeeRequests($user, $targetEmployee)) {
                    Log::info('LeaveAdjustmentController::createAdjustment', [
                        'success' => false,
                        'user_id' => $user->user_id,
                        'company_id' => $effectiveCompanyId ?? null,
                        'message' => 'ليس لديك صلاحية لإنشاء تسويات لهذا الموظف'
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'ليس لديك صلاحية لإنشاء تسويات لهذا الموظف'
                    ], 403);
                }
            }

            $dto = CreateLeaveAdjustmentDTO::fromRequest(
                $validated,
                $effectiveCompanyId,
                $validated['employee_id'] ?? $user->user_id,
                $user->user_id // Pass creator ID
            );

            $adjustment = $this->leaveAdjustmentService->createAdjust($dto);

            Log::info('LeaveAdjustmentController::createAdjustment', [
                'success' => true,
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'message' => 'تم إنشاء طلب التسوية بنجاح'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب التسوية بنجاح',
                'created by' => $user->full_name,
                'data' => $adjustment
            ], 201);
        } catch (\Exception $e) {
            Log::error('LeaveAdjustmentController::createAdjustment failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
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
     * @OA\Post(
     *     path="/api/leaves/adjustments/{id}/approve-or-reject",
     *     summary="Approve or Reject leave adjustment (Managers/HR only)",
     *     description="الموافقة على أو رفض طلب التسوية",
     *     tags={"Leave Adjustments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", enum={"approve", "reject"}),
     *         description="الإجراء: approve للموافقة أو reject للرفض"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="remarks", type="string", example="موافق على الطلب", description="ملاحظات (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustment approved or rejected successfully"
     *     )
     * )
     */
    public function approveAdjustment(ApproveLeaveAdjustmentRequest $request, int $id)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Check permissions - either leave_adjustment4 (full approval) or leave_adjustment (view + hierarchy approval)
            $hasFullApprovalPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment4');
            $hasViewPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment');

            if (!$hasFullApprovalPermission && !$hasViewPermission) {
                Log::info('LeaveAdjustmentController::approveAdjustment', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'غير مصرح لك بمراجعة الطلبات'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بمراجعة الطلبات'
                ], 403);
            }

            // التحقق من وجود التسوية والصلاحيات الهرمية
            $adjustment = $this->leaveAdjustmentService->showLeaveAdjustment($id, $effectiveCompanyId);
            if (!$adjustment) {
                Log::info('LeaveAdjustmentController::approveAdjustment', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'تسوية الإجازة غير موجودة أو لا تنتمي إلى هذه الشركة'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'تسوية الإجازة غير موجودة أو لا تنتمي إلى هذه الشركة'
                ], 404);
            }

            // التحقق من صلاحيات المستوى الهرمي إذا لم يكن صاحب شركة
            if ($user->user_type !== 'company' && $adjustment->employee_id !== $user->user_id) {
                $employee = User::find($adjustment->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    Log::info('LeaveAdjustmentController::approveAdjustment', [
                        'success' => false,
                        'user_id' => $user->user_id,
                        'company_id' => $effectiveCompanyId ?? null,
                        'message' => 'ليس لديك صلاحية لمراجعة هذه التسوية'
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'ليس لديك صلاحية لمراجعة هذه التسوية'
                    ], 403);
                }
            }

            $action = $request->input('action'); // approve or reject

            if ($action === 'approve') {
                $adjustment = $this->leaveAdjustmentService->approveAdjustment(
                    $id,
                    $effectiveCompanyId,
                    $user->user_id
                );

                Log::info('LeaveAdjustmentController::approveAdjustment', [
                    'success' => true,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'تم الموافقة على طلب التسوية بنجاح'
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'تم الموافقة على طلب التسوية بنجاح',
                    'data' => $adjustment,
                    'created by' => $user->full_name
                ]);
            } else {
                // رفض التسوية
                $remarks = $request->input('remarks', 'تم رفض الطلب');

                $adjustment = $this->leaveAdjustmentService->rejectAdjustment(
                    $id,
                    $effectiveCompanyId,
                    $user->user_id,
                    $remarks
                );

                Log::info('LeaveAdjustmentController::approveAdjustment', [
                    'success' => true,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'تم رفض طلب التسوية بنجاح'
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'تم رفض طلب التسوية بنجاح',
                    'data' => $adjustment,
                    'created by' => $user->full_name
                ]);
            }
        } catch (\Exception $e) {
            Log::error('LeaveAdjustmentController::approveAdjustment failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في مراجعة طلب التسوية',
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
     *     tags={"Leave Adjustments"},
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
     *             @OA\Property(property="adjustment_date", type="string", format="date", example="2025-12-01")
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
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment2');
            if (!$isUserHasThisPermission) {
                Log::info('LeaveAdjustmentController::updateAdjustment', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'غير مصرح لك بتعديل تسويات الإجازات'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل تسويات الإجازات'
                ], 403);
            }

            $dto = UpdateLeaveAdjustmentDTO::fromRequest($request->validated());
            $adjustment = $this->leaveAdjustmentService->updateAdjustment($id, $dto, $user);

            Log::info('LeaveAdjustmentController::updateAdjustment', [
                'success' => true,
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'message' => 'تم تحديث تسوية الإجازة بنجاح'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث تسوية الإجازة بنجاح',
                'data' => $adjustment->toArray()
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveAdjustmentController::updateAdjustment failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
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
     *     tags={"Leave Adjustments"},
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
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment3');
            if (!$isUserHasThisPermission) {
                Log::info('LeaveAdjustmentController::cancelAdjustment', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'غير مصرح لك بإلغاء تسويات الإجازات'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإلغاء تسويات الإجازات'
                ], 403);
            }

            $this->leaveAdjustmentService->cancelAdjustment($id, $user);

            Log::info('LeaveAdjustmentController::cancelAdjustment', [
                'success' => true,
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'message' => 'تم إلغاء تسوية الإجازة بنجاح'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء تسوية الإجازة بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveAdjustmentController::cancelAdjustment failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
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
     *     path="/api/leaves/adjustments/{id}",
     *     summary="Get a specific leave adjustment",
     *     tags={"Leave Adjustments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave adjustment retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="adjustment_id", type="integer", example=501),
     *                 @OA\Property(property="company_id", type="integer", example=36),
     *                 @OA\Property(property="employee_id", type="integer", example=37),
     *                 @OA\Property(property="duty_employee_id", type="integer", nullable=true, example=118),
     *                 @OA\Property(property="leave_type_id", type="integer", example=323),
     *                 @OA\Property(property="adjust_hours", type="number", format="float", example=8.0),
     *                 @OA\Property(property="reason_adjustment", type="string", example="تسوية رصيد سنوية"),
     *                 @OA\Property(property="status", type="integer", enum={0,1,2}, example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-01 08:30:00"),
     *                 @OA\Property(property="adjustment_date", type="string", format="date", example="2025-12-01"),
     *                 @OA\Property(
     *                     property="employee",
     *                     type="object",
     *                     @OA\Property(property="user_id", type="integer", example=37),
     *                     @OA\Property(property="full_name", type="string", example="محمد أحمد"),
     *                     @OA\Property(property="email", type="string", example="m.ahmed@example.com")
     *                 ),
     *                 @OA\Property(
     *                     property="dutyEmployee",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="user_id", type="integer", example=118),
     *                     @OA\Property(property="full_name", type="string", example="خالد سالم"),
     *                     @OA\Property(property="email", type="string", example="k.salem@example.com")
     *                 ),
     *                 @OA\Property(
     *                     property="leaveType",
     *                     type="object",
     *                     @OA\Property(property="constants_id", type="integer", example=323),
     *                     @OA\Property(property="category_name", type="string", example="سنوية"),
     *                     @OA\Property(property="field_two", type="number", example=21, description="عدد الأيام")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave adjustment not found"
     *     )
     * )
     */
    public function showLeaveAdjustment(int $id, Request $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // التحقق من الصلاحيات
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment');
            if (!$isUserHasThisPermission) {
                Log::info('LeaveAdjustmentController::showLeaveAdjustment', [
                    'success' => false,
                    'user_id' => $user->user_id,
                    'company_id' => $effectiveCompanyId ?? null,
                    'message' => 'غير مصرح لك بعرض تفاصيل تسويات الإجازات'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض تفاصيل تسويات الإجازات'
                ], 403);
            }

            $adjustment = $this->leaveAdjustmentService->showLeaveAdjustment($id, $effectiveCompanyId, $user);

            Log::info('LeaveAdjustmentController::showLeaveAdjustment', [
                'success' => true,
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'message' => 'تم جلب تفاصيل تسويات الإجازات بنجاح'
            ]);
            return response()->json([
                'success' => true,
                'data' => $adjustment
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveAdjustmentController::showLeaveAdjustment failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId ?? null,
                'created by' => $user->full_name ?? 'unknown'
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getMessage() === 'تسوية الإجازة غير موجودة أو لا تنتمي إلى هذه الشركة' ? 404 : 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/leaves/adjustments/enums",
     *     summary="Get leave enums as string and numeric values",
     *     tags={"Leave Adjustments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="statuses", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function getLeaveAdjustmentsEnums()
    {
        try {
            $enums = $this->leaveAdjustmentService->getLeaveEnums();

            Log::info('LeaveAdjustmentController::getLeaveAdjustmentsEnums', [
                'success' => true,
                'company_id' => $effectiveCompanyId ?? null,
                'message' => 'تم جلب قوائم حالات التسوية بنجاح'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم جلب قوائم حالات التسوية بنجاح',
                'data' => $enums
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveAdjustmentController::getLeaveAdjustmentsEnums failed', [
                'error' => $e->getMessage(),
                'company_id' => $effectiveCompanyId ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب حالات القوائم',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
