<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveTypeService;
use App\Services\SimplePermissionService;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\DTOs\Leave\UpdateLeaveTypeDTO;
use App\DTOs\LeaveType\LeaveTypeFilterDTO;
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
 *     summary="Get all leave types with search and pagination",
 *     tags={"Leave Type Management"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string"),
 *         description="Search term for leave type name or description"
 *     ),
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="integer", default=15),
 *         description="Number of items per page"
 *     ),
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="integer", default=1),
 *         description="Page number"
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Leave types retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="تم جلب أنواع الإجازات بنجاح"),
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
 *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض أنواع الإجازات")
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
public function index(Request $request)
{
    try {
        $user = Auth::user();
        
        // التحقق من الصلاحيات
        if (!$this->permissionService->checkPermission($user, 'view_leave_types')) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بعرض أنواع الإجازات'
            ], 403);
        }

        // الحصول على معرف الشركة الفعال
        $effectiveCompanyId = $request->attributes->get('effective_company_id') ?? $user->company_id;

        $filters = LeaveTypeFilterDTO::fromRequest($request);
        // جلب البيانات مع الترقيم
        $leaveTypes = $this->leaveTypeService->getActiveLeaveTypes($effectiveCompanyId, $filters->toArray());

        return response()->json([
            'success' => true,
            'data' => $leaveTypes
        ]);

    } catch (\Exception $e) {
        Log::error('LeaveTypeController::index - Error: ' . $e->getMessage(), [
            'user_id' => $user->id ?? null,
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء استرجاع أنواع الإجازات',
            'error' => config('app.debug') ? $e->getMessage() : null
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

        // إضافة معرف نوع الإجازة إلى البيانات
        $data = $request->validated();
        $data['leave_type_id'] = $id;  // هنا نضيف معرف نوع الإجازة

        $dto = UpdateLeaveTypeDTO::fromRequest($data);

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
