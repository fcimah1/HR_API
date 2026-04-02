<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\CreateDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Services\DepartmentService;
use App\Services\SimplePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Departments",
 *     description="إدارة الأقسام"
 * )
 * 
 * @OA\Schema(
 *     schema="Department",
 *     title="Department",
 *     description="Department model",
 *     @OA\Property(property="department_id", type="integer", example=1),
 *     @OA\Property(property="department_name", type="string", example="الموارد البشرية"),
 *     @OA\Property(property="department_head", type="integer", example=10),
 *     @OA\Property(property="company_id", type="integer", example=1),
 *     @OA\Property(property="added_by", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class DepartmentController extends Controller
{
    use \App\Traits\ApiResponseTrait;

    public function __construct(
        protected DepartmentService $departmentService,
        protected SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/departments",
     *     summary="استرجاع الأقسام",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="البحث بالاسم",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="paginate",
     *         in="query",
     *         description="تفعيل الترقيم (true/false)",
     *         required=false,
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="عدد النتائج في الصفحة",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="رقم الصفحة",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم استرجاع الأقسام بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Department"))
     *         )
     *     ),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function index(\App\Http\Requests\Department\DepartmentSearchRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $filters = $request->validated();

            $departments = $this->departmentService->getDepartments($companyId, $filters);

            if ($departments instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                return $this->paginatedResponse(
                    $departments,
                    'تم استرجاع الأقسام بنجاح',
                    DepartmentResource::class
                );
            }

            return $this->successResponse(
                DepartmentResource::collection($departments),
                'تم استرجاع الأقسام بنجاح'
            );
        } catch (\Exception $e) {
            Log::error('DepartmentController::index failed', ['error' => $e->getMessage()]);
            return $this->handleException($e, 'DepartmentController::index');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/departments",
     *     summary="إنشاء قسم جديد",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"department_name"},
     *             @OA\Property(property="department_name", type="string", example="الموارد البشرية"),
     *             @OA\Property(property="department_head", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إنشاء القسم بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function store(CreateDepartmentRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $data = $request->validated();
            $data['company_id'] = $companyId;
            $data['added_by'] = $user->user_id;

            $department = $this->departmentService->createDepartment($data);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء القسم بنجاح',
                'data' => new DepartmentResource($department->loadCount('userDetails'))
            ], 201);
        } catch (\Exception $e) {
            Log::error('DepartmentController::store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/departments/{id}",
     *     summary="استرجاع تفاصيل القسم",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="تم استرجاع تفاصيل القسم بنجاح"),
     *     @OA\Response(response=404, description="القسم غير موجود"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $department = $this->departmentService->getDepartment($id, $companyId);

            if (!$department) {
                return response()->json(['success' => false, 'message' => 'القسم غير موجود'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم استرجاع تفاصيل القسم بنجاح',
                'data' => new DepartmentResource($department)
            ]);
        } catch (\Exception $e) {
            Log::error('DepartmentController::show failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/departments/{id}",
     *     summary="تعديل قسم",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"department_name"},
     *             @OA\Property(property="department_name", type="string", example="الموارد البشرية"),
     *             @OA\Property(property="department_head", type="integer", example=1)
     *         )
     *     ),    
     *     @OA\Response(response=200, description="تم تحديث القسم بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function update(int $id, UpdateDepartmentRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $department = $this->departmentService->updateDepartment($id, $companyId, $request->validated());

            if (!$department) {
                return response()->json(['success' => false, 'message' => 'القسم غير موجود'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث القسم بنجاح',
                'data' => new DepartmentResource($department)
            ]);
        } catch (\Exception $e) {
            Log::error('DepartmentController::update failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/departments/{id}",
     *     summary="حذف قسم",
     *     tags={"Departments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف القسم بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $success = $this->departmentService->deleteDepartment($id, $companyId);

            if (!$success) {
                return response()->json(['success' => false, 'message' => 'القسم غير موجود'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم حذف القسم بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('DepartmentController::destroy failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
