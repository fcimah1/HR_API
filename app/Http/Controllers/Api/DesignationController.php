<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designation\CreateDesignationRequest;
use App\Http\Requests\Designation\UpdateDesignationRequest;
use App\Http\Resources\DesignationResource;
use App\Services\DesignationService;
use App\Services\SimplePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Designations",
 *     description="إدارة المسميات الوظيفية"
 * )
 * 
 * @OA\Schema(
 *     schema="Designation",
 *     title="Designation",
 *     description="Designation model",
 *     @OA\Property(property="designation_id", type="integer", example=1),
 *     @OA\Property(property="department_id", type="integer", example=1),
 *     @OA\Property(property="company_id", type="integer", example=1),
 *     @OA\Property(property="hierarchy_level", type="integer", example=5),
 *     @OA\Property(property="designation_name", type="string", example="محاسب"),
 *     @OA\Property(property="description", type="string", example="وصف المسمى الوظيفي"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class DesignationController extends Controller
{
    use \App\Traits\ApiResponseTrait;

    public function __construct(
        protected DesignationService $designationService,
        protected SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/designations",
     *     summary="استرجاع المسميات الوظيفية",
     *     tags={"Designations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="department_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="paginate", in="query", required=false, @OA\Schema(type="boolean", default=true)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Response(response=200, description="تم استرجاع المسميات الوظيفية بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function index(\App\Http\Requests\Designation\DesignationSearchRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $filters = $request->validated();

            $designations = $this->designationService->getDesignations($companyId, $filters);

            if ($designations instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                return $this->paginatedResponse(
                    $designations,
                    'تم استرجاع المسميات الوظيفية بنجاح',
                    DesignationResource::class
                );
            }

            return $this->successResponse(
                DesignationResource::collection($designations),
                'تم استرجاع المسميات الوظيفية بنجاح'
            );
        } catch (\Exception $e) {
            Log::error('DesignationController::index failed', ['error' => $e->getMessage()]);
            return $this->handleException($e, 'DesignationController::index');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/designations",
     *     summary="إنشاء مسمى وظيفي جديد",
     *     tags={"Designations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"department_id", "designation_name", "hierarchy_level"},
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="designation_name", type="string", example="محاسب"),
     *             @OA\Property(property="hierarchy_level", type="integer", example=5),
     *             @OA\Property(property="description", type="string", example="وصف المسمى الوظيفي")
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إنشاء المسمى الوظيفي بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function store(CreateDesignationRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $data = $request->validated();
            $data['company_id'] = $companyId;

            $designation = $this->designationService->createDesignation($data);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء المسمى الوظيفي بنجاح',
                'data' => new DesignationResource($designation->loadCount('userDetails'))
            ], 201);
        } catch (\Exception $e) {
            Log::error('DesignationController::store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/designations/{id}",
     *     summary="استرجاع تفاصيل المسمى الوظيفي",
     *     tags={"Designations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم استرجاع تفاصيل المسمى الوظيفي بنجاح"),
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

            $designation = $this->designationService->getDesignation($id, $companyId);

            if (!$designation) {
                return response()->json(['success' => false, 'message' => 'المسمى الوظيفي غير موجود'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم استرجاع تفاصيل المسمى الوظيفي بنجاح',
                'data' => new DesignationResource($designation)
            ]);
        } catch (\Exception $e) {
            Log::error('DesignationController::show failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/designations/{id}",
     *     summary="تعديل مسمى وظيفي",
     *     tags={"Designations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/Designation")),
     *     @OA\Response(response=200, description="تم تحديث المسمى الوظيفي بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function update(int $id, UpdateDesignationRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $designation = $this->designationService->updateDesignation($id, $companyId, $request->validated());

            if (!$designation) {
                return response()->json(['success' => false, 'message' => 'المسمى الوظيفي غير موجود'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المسمى الوظيفي بنجاح',
                'data' => new DesignationResource($designation)
            ]);
        } catch (\Exception $e) {
            Log::error('DesignationController::update failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/designations/{id}",
     *     summary="حذف مسمى وظيفي",
     *     tags={"Designations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف المسمى الوظيفي بنجاح"),
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

            $success = $this->designationService->deleteDesignation($id, $companyId);

            if (!$success) {
                return response()->json(['success' => false, 'message' => 'المسمى الوظيفي غير موجود'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المسمى الوظيفي بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('DesignationController::destroy failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
