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
     *         description="Pending approvals retrieved"
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
     *             @OA\Property(property="module_option", type="string"),
     *             @OA\Property(property="module_key_id", type="string"),
     *             @OA\Property(property="status", type="string", enum={"approve", "reject"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Approval processed"
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
     *         description="Approval history retrieved"
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
