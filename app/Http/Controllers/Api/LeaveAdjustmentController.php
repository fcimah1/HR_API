<?php

namespace App\Http\Controllers\Api;

use App\DTOs\LeaveAdjustment\CreateLeaveAdjustmentDTO;
use App\DTOs\LeaveAdjustment\LeaveAdjustmentFilterDTO;
use App\DTOs\LeaveAdjustment\UpdateLeaveAdjustmentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveAdjustment\ApproveLeaveAdjustmentRequest;
use App\Http\Requests\LeaveAdjustment\CreateLeaveAdjustmentRequest;
use App\Http\Requests\LeaveAdjustment\UpdateLeaveAdjustmentRequest;
use App\Services\LeaveAdjustmentService;
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
        private readonly LeaveAdjustmentService $leaveService,
        private readonly SimplePermissionService $permissionService
    ) {
        $this->simplePermissionService = $permissionService;
    }


    /**
     * @OA\Get(
     *     path="/api/leaves/adjustments",
     *     summary="Get leave adjustments",
     *     tags={"Leave Adjustments"},
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

            $result = $this->leaveService->getPaginatedAdjustments($filters, $user);

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
     *     tags={"Leave Adjustments"},
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

            $adjustment = $this->leaveService->createAdjust($dto);

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
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"action"},
     *             @OA\Property(property="action", type="string", enum={"approve", "reject"}, example="approve", description="الإجراء: approve للموافقة أو reject للرفض"),
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
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment4');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بمراجعة الطلبات'
                ], 403);
            }

            // استخدام المعرف الفعلي للشركة لدعم مالك الشركة
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $action = $request->input('action'); // approve or reject

            if ($action === 'approve') {
                $adjustment = $this->leaveService->approveAdjustment(
                    $id,
                    $companyId,
                    $user->user_id
                );

                return response()->json([
                    'success' => true,
                    'message' => 'تم الموافقة على طلب التسوية بنجاح',
                    'data' => $adjustment,
                    'created by' => $user->full_name
                ]);
            } else {
                // رفض التسوية
                $remarks = $request->input('remarks', 'تم رفض الطلب');

                $adjustment = $this->leaveService->rejectAdjustment(
                    $id,
                    $companyId,
                    $user->user_id,
                    $remarks
                );

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
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment3');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإلغاء تسويات الإجازات'
                ], 403);
            }
            $this->leaveService->cancelAdjustment($id, $user->user_id);

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
     *         description="Leave adjustment retrieved successfully"
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

            // التحقق من الصلاحيات
            $isUserHasThisPermission = $this->simplePermissionService->checkPermission($user, 'leave_adjustment');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض تفاصيل تسويات الإجازات'
                ], 403);
            }

            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $request->attributes->get('effective_company_id');

            $adjustment = $this->leaveService->showLeaveAdjustment($id, $effectiveCompanyId);

            return response()->json([
                'success' => true,
                'data' => $adjustment
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveAdjustmentController::showLeaveAdjustment failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name ?? 'unknown'
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getMessage() === 'تسوية الإجازة غير موجودة أو لا تنتمي إلى هذه الشركة' ? 404 : 500);
        }
    }
}
