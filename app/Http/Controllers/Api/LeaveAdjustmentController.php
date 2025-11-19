<?php

namespace App\Http\Controllers\Api;

use App\DTOs\LeaveAdjustment\CreateLeaveAdjustmentDTO;
use App\DTOs\LeaveAdjustment\LeaveAdjustmentFilterDTO;
use App\DTOs\LeaveAdjustment\UpdateLeaveAdjustmentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveAdjustment\CreateLeaveAdjustmentRequest;
use App\Http\Requests\LeaveAdjustment\UpdateLeaveAdjustmentRequest;
use App\Services\LeaveAdjustmentService;
use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

            Log::info('LeaveService::createAdjustment start', [
                'dto' => $dto->toArray(),
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId
            ]);

            $adjustment = $this->leaveService->createAdjust($dto);

            Log::info('LeaveService::createAdjustment successed', [
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

    // /**
    //  * @OA\Post(
    //  *     path="/api/leaves/settlement",
    //  *     summary="Settle employee's accrued leave balance (Encashment or Take Leave)",
    //  *     tags={"Leave Management"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\JsonContent(
    //  *             required={"leave_type_id","hours_to_settle","settlement_type"},
    //  *             @OA\Property(property="leave_type_id", type="integer", example=323, description="معرف نوع الإجازة المراد تسويتها"),
    //  *             @OA\Property(property="hours_to_settle", type="number", format="float", example=40.5, description="عدد الساعات المراد تسويتها"),
    //  *             @OA\Property(property="settlement_type", type="string", example="encashment", enum={"encashment", "take_leave"}, description="نوع التسوية: صرف نقدي (encashment) أو أخذ إجازة (take_leave)")
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="Leave settlement processed successfully",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="تمت تسوية 40.5 ساعة بنجاح كصرف نقدي. تم تحديث رصيد الإجازات."),
    //  *             @OA\Property(property="data", type="object",
    //  *                 @OA\Property(property="settlement_type", type="string"),
    //  *                 @OA\Property(property="hours_settled", type="number"),
    //  *                 @OA\Property(property="old_balance", type="number"),
    //  *                 @OA\Property(property="new_balance", type="number"),
    //  *                 @OA\Property(property="record", type="object")
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=422,
    //  *         description="Validation or insufficient balance error",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=false),
    //  *             @OA\Property(property="message", type="string", example="الرصيد المتاح (20.0 ساعة) غير كافٍ لتسوية 40.5 ساعة.")
    //  *         )
    //  *     )
    //  * )
    //  */



    // public function settleLeave(CreateLeaveSettlementRequest $request)
    // {
    //     $user = Auth::user();

    //     try {
    //         // الحصول على معرف الشركة الفعلي من attributes
    //         $effectiveCompanyId = $request->attributes->get('effective_company_id') ?? $user->company_id;
            
    //         $dto = CreateLeaveSettlementDTO::fromRequest(
    //             $request->validated(),
    //             $effectiveCompanyId,
    //             $user->user_id
    //         );

    //         $result = $this->leaveService->handleLeaveSettlement($dto);

    //         Log::info('LeaveController::settleLeave', [
    //             'success' => true,
    //             'created by' => $user->full_name,
    //             'result' => $result
    //         ]);

    //         return response()->json([
    //             'success' => true,
    //             'message' => $result['message'],
    //             'data' => $result
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('LeaveController::settleLeave failed', [
    //             'error' => $e->getMessage(),
    //             'created by' => $user->full_name
    //         ]);
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 422); // Use 422 for business logic errors like insufficient balance
    //     }
    // }

}
