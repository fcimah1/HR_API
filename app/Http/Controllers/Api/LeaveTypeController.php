<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveTypeService;
use App\Services\SimplePermissionService;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\DTOs\Leave\UpdateLeaveTypeDTO;
use App\Http\Requests\Leave\CreateLeaveTypeRequest;
use App\Http\Requests\Leave\UpdateLeaveTypeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Leave Type Management",
 *     description="Leave types management"
 * )
 */
class LeaveTypeController extends Controller
{
    protected $leaveTypeService;
    protected $permissionService;
    public function __construct(
          LeaveTypeService $leaveTypeService,
          SimplePermissionService $permissionService
    ) {
        $this->leaveTypeService = $leaveTypeService;
        $this->permissionService = $permissionService;
    }

    /**
     * @OA\Get(
     *     path="/api/leave-types",
     *     summary="Get available leave types",
     *     tags={"Leave Type Management"},
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
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, ' ');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض أنواع الإجازات'
                ], 403);
            }

            $effectiveCompanyId = $request->attributes->get('effective_company_id') ?? $user->company_id;

            $leaveTypes = $this->leaveTypeService->getActiveLeaveTypes($effectiveCompanyId);

            return response()->json([
                'success' => true,
                'data' => $leaveTypes,
                'effectiveCompanyId' => $effectiveCompanyId,
                'message' => 'تم جلب أنواع الإجازات بنجاح',
                'created_by' => $user->full_name,
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveTypeController::index failed', [
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
     *     path="/api/leave-types",
     *     summary="Create a new leave type (HR/Admin only)",
     *     tags={"Leave Type Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_name","leave_days"},
     *             @OA\Property(property="leave_type_name", type="string", example="إجازة دراسية"),
     *             @OA\Property(property="leave_days", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave type created successfully"
     *     )
     * )
     */
    public function storeLeaveType(CreateLeaveTypeRequest $request)
    {
        try {
            $user = Auth::user();

            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'leave_type2');
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

            $leaveType = $this->leaveTypeService->createLeaveType($dto);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء نوع الإجازة بنجاح',
                'data' => $leaveType,
                'created by' => $user->full_name
            ], 201);
        } catch (\Exception $e) {
            Log::error('LeaveTypeController::store failed', [
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
     * @OA\Get(
     *     path="/api/leave-types/{id}",
     *     summary="Get specific leave type details",
     *     tags={"Leave Type Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave type details retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave type not found"
     *     )
     * )
     */
    public function showLeaveType(int $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'leave_type1');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض أنواع الإجازات'
                ], 403);
            }

            $leaveType = $this->leaveTypeService->getLeaveType($id);

            return response()->json([
                'success' => true,
                'data' => $leaveType
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/leave-types/{id}",
     *     summary="Update a leave type",
     *     tags={"Leave Type Management"},
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
     *             @OA\Property(property="leave_type_name", type="string", example="Updated Leave Name"),
     *             @OA\Property(property="leave_days", type="integer", example=15)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave type updated successfully"
     *     )
     * )
     */
    public function updateLeaveType(UpdateLeaveTypeRequest $request, int $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'leave_type3');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل أنواع الإجازات'
                ], 403);
            }

            $dto = UpdateLeaveTypeDTO::fromRequest($request->validated());

            $leaveType = $this->leaveTypeService->updateLeaveType($dto);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث نوع الإجازة بنجاح',
                'data' => $leaveType
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveTypeController::update failed', [
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
     *     path="/api/leave-types/{id}",
     *     summary="Delete a leave type",
     *     tags={"Leave Type Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave type deleted successfully"
     *     )
     * )
     */
    public function destroyLeaveType(int $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'leave_type4');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بحذف أنواع الإجازات'
                ], 403);
            }

            $this->leaveTypeService->deleteLeaveType($id, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف نوع الإجازة بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('LeaveTypeController::destroy failed', [
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
