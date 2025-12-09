<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApprovalWorkflowService;
use App\Services\SimplePermissionService;
use App\DTOs\Notification\ApprovalActionDTO;
use App\Http\Requests\Notification\ApprovalActionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Approvals",
 *     description="API for multi-level approval workflow"
 * )
 */
class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalWorkflowService $approvalService,
        protected SimplePermissionService $permissionService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/approvals/pending",
     *     summary="Get pending approvals for current user",
     *     tags={"Approvals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="module_option",
     *         in="query",
     *         description="Filter by module (optional)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pending approvals retrieved",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="approval_id", type="integer", example=4521),
     *                     @OA\Property(property="module_option", type="string", example="leave_settings"),
     *                     @OA\Property(property="module_key_id", type="string", example="325"),
     *                     @OA\Property(property="status", type="integer", example=0, description="0=pending,1=approved,2=rejected"),
     *                     @OA\Property(property="approval_level", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=5)
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
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function getPending(Request $request)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $moduleOption = $request->query('module_option');

            $approvals = $this->approvalService->getPendingApprovalsForUser(
                $user->user_id,
                $companyId,
                $moduleOption
            );

            return response()->json([
                'success' => true,
                'data' => $approvals,
                'count' => count($approvals),
            ]);
        } catch (\Exception $e) {
            Log::error('ApprovalController::getPending failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/approvals/process",
     *     summary="Process approval or rejection",
     *     tags={"Approvals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"module_option", "module_key_id", "status"},
     *             @OA\Property(property="module_option", type="string", example="leave", description="نوع الطلب (leave, travel, etc)"),
     *             @OA\Property(property="module_key_id", type="string", example="123", description="معرف الطلب"),
     *             @OA\Property(property="status", type="string", enum={"approve", "reject"}, example="approve", description="الحالة: موافقة أو رفض"),
     *             @OA\Property(property="remarks", type="string", example="موافق", description="ملاحظات (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Approval processed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
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
     *         description="Forbidden - No permission to approve",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="ليس لديك صلاحية للموافقة على هذا الطلب")
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
     *             @OA\Property(property="message", type="string", example="فشل في معالجة الموافقة")
     *         )
     *     )
     * )
     */
    public function processApproval(ApprovalActionRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = ApprovalActionDTO::fromRequest($request, $user->user_id, $companyId);

            // Check if user can approve
            if (!$this->approvalService->canUserApprove(
                $user->user_id,
                $dto->moduleOption,
                $dto->moduleKeyId,
                $companyId
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'ليس لديك صلاحية للموافقة على هذا الطلب'
                ], 403);
            }

            $result = $this->approvalService->processApproval($dto);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('ApprovalController::processApproval failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/approvals/history/{module}/{id}",
     *     summary="Get approval history for a request",
     *     tags={"Approvals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="module",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Approval history retrieved",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="approval_id", type="integer", example=9876),
     *                     @OA\Property(property="staff_id", type="integer", example=37),
     *                     @OA\Property(property="staff_name", type="string", example="محمد أحمد"),
     *                     @OA\Property(property="status", type="integer", example=1, description="1=approved,2=rejected"),
     *                     @OA\Property(property="status_text", type="string", example="موافق"),
     *                     @OA\Property(property="approval_level", type="integer", example=2),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 09:00:00")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=3)
     *         )
     *     )
     * )
     */
    public function getHistory(string $module, string $id)
    {
        try {
            $history = $this->approvalService->getApprovalHistory($module, $id);

            return response()->json([
                'success' => true,
                'data' => $history,
                'count' => count($history),
            ]);
        } catch (\Exception $e) {
            Log::error('ApprovalController::getHistory failed', [
                'error' => $e->getMessage(),
                'module' => $module,
                'id' => $id,
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
