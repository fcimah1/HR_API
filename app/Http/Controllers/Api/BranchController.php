<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\DTOs\Branch\BranchFilterDTO;
use App\DTOs\Branch\CreateBranchDTO;
use App\DTOs\Branch\UpdateBranchDTO;
use App\Http\Requests\Branch\BranchSearchRequest;
use App\Http\Requests\Branch\CreateBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Services\BranchService;
use App\Services\SimplePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Branches",
 *     description="إدارة الفروع"
 * )
 */
class BranchController extends Controller
{
    use \App\Traits\ApiResponseTrait;

    public function __construct(
        protected BranchService $branchService,
        protected SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/branches",
     *     summary="استرجاع فروع الشركة",
     *     description="قائمة بالفروع الخاصة بالشركة مع إمكانية البحث",
     *     tags={"Branches"},
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
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         description="البحث برقم الفرع",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم استرجاع الفروع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="branch_id", type="integer", example=1),
     *                     @OA\Property(property="branch_name", type="string", example="الفرع الرئيسي"),
     *                     @OA\Property(property="coordinates", type="string", example="POLYGON((31.2357 30.0444, 31.2533 30.0695, 31.2480 30.0691, 31.2357 30.0444))", description="الإحداثيات بصيغة (lat, lng) أو WKT (POINT, POLYGON)")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة")
     * )
     */
    public function index(BranchSearchRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $filters = BranchFilterDTO::fromRequest($request)->toArray();

            $branches = $this->branchService->getBranches($companyId, $filters);

            Log::info('BranchController::index success', [
                'user_id' => $user->user_id ?? null,
                'company_id' => $companyId,
                'filters' => $filters,
            ]);

            if ($branches instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                return $this->paginatedResponse(
                    $branches,
                    'تم استرجاع الفروع بنجاح',
                    BranchResource::class
                );
            }

            return $this->successResponse(
                BranchResource::collection($branches),
                'تم استرجاع الفروع بنجاح'
            );
        } catch (\Exception $e) {
            Log::error('BranchController::index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->user_id ?? null
            ]);
            return $this->handleException($e, 'BranchController::index');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/branches/{id}",
     *     summary="استرجاع تفاصيل الفرع",
     *     description="تفاصيل فرع محدد تابع للشركة",
     *     tags={"Branches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="رقم الفرع",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم استرجاع تفاصيل الفرع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="branch_id", type="integer", example=1),
     *                 @OA\Property(property="branch_name", type="string", example="الفرع الرئيسي"),
     *                 @OA\Property(property="coordinates", type="string", example="POLYGON((31.2357 30.0444, 31.2533 30.0695, 31.2480 30.0691, 31.2357 30.0444))", description="الإحداثيات بصيغة (lat, lng) أو WKT (POINT, POLYGON)")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="الفرع غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $branch = $this->branchService->getBranch($id, $companyId);

            if (!$branch) {
                Log::warning('BranchController::show not found', [
                    'user_id' => $user->id ?? null,
                    'company_id' => $companyId,
                    'branch_id' => $id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'الفرع غير موجود'
                ], 404);
            }

            Log::info('BranchController::show success', [
                'user_id' => $user->id ?? null,
                'company_id' => $companyId,
                'branch_id' => $id
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم استرجاع تفاصيل الفرع بنجاح',
                'data' => new BranchResource($branch)
            ]);
        } catch (\Exception $e) {
            Log::error('BranchController::show failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/branches",
     *     summary="إنشاء فرع جديد",
     *     tags={"Branches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"branch_name"},
     *             @OA\Property(property="branch_name", type="string", example="فرع القاهرة"),
     *             @OA\Property(
     *                 property="coordinates", 
     *                 type="string", 
     *                 example="POLYGON((31.2357 30.0444, 31.2533 30.0695, 31.2480 30.0691, 31.2357 30.0444))", 
     *                 description="إحداثيات الموقع. تقبل الصيغ التالية: 'lat, lng' (مثل 30.0444, 31.2357) أو صيغة WKT (مثل POINT(31.2357 30.0444) أو POLYGON((...)))"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إنشاء الفرع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء الفرع بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="فشل التحقق"),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function store(CreateBranchRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = CreateBranchDTO::fromRequest($request->validated(), $companyId, $user->user_id);
            $branch = $this->branchService->createBranch($dto);

            Log::info('BranchController::store success', [
                'user_id' => $user->id ?? null,
                'company_id' => $companyId,
                'branch_id' => $branch->branch_id
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الفرع بنجاح',
                'data' => new BranchResource($branch)
            ], 201);
        } catch (\Exception $e) {
            Log::error('BranchController::store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/branches/{id}",
     *     summary="تعديل فرع",
     *     tags={"Branches"},
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
     *             required={"branch_name"},
     *             @OA\Property(property="branch_name", type="string", example="فرع الجيزة"),
     *             @OA\Property(
     *                 property="coordinates", 
     *                 type="string", 
     *                 example="POLYGON((31.2010 30.0009, 31.1970 30.0006, 31.2030 29.9993, 31.2010 30.0009))",
     *                 description="إحداثيات الموقع. تقبل الصيغ التالية: 'lat, lng' (مثل 30.0444, 31.2357) أو صيغة WKT (مثل POINT(31.2357 30.0444) أو POLYGON((...)))"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تحديث الفرع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث الفرع بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="الفرع غير موجود"),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function update(int $id, UpdateBranchRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = UpdateBranchDTO::fromRequest($request->validated(), $id, $companyId, $user->user_id);
            $branch = $this->branchService->updateBranch($id, $companyId, $dto);

            Log::info('BranchController::update success', [
                'user_id' => $user->id ?? null,
                'company_id' => $companyId,
                'branch_id' => $id
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الفرع بنجاح',
                'data' => new BranchResource($branch)
            ]);
        } catch (\Exception $e) {
            Log::error('BranchController::update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/branches/{id}",
     *     summary="حذف فرع",
     *     tags={"Branches"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم حذف الفرع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم حذف الفرع بنجاح")
     *         )
     *     ),
     *     @OA\Response(response=404, description="الفرع غير موجود"),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $success = $this->branchService->deleteBranch($id, $companyId);

            if (!$success) {
                Log::warning('BranchController::destroy not found', [
                    'user_id' => $user->id ?? null,
                    'company_id' => $companyId,
                    'branch_id' => $id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'الفرع غير موجود أو لا يمكن حذفه'
                ], 404);
            }

            Log::info('BranchController::destroy success', [
                'user_id' => $user->id ?? null,
                'company_id' => $companyId,
                'branch_id' => $id
            ]);
            return response()->json([
                'success' => true,
                'message' => 'تم حذف الفرع بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('BranchController::destroy failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
