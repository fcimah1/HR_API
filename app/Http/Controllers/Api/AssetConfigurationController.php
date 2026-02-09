<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreAssetConfigurationRequest;
use App\Models\ErpConstant;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Added Log
use OpenApi\Annotations as OA;
use App\Services\AssetConfigurationService; // Added Service usage

/**
 * @OA\Tag(
 *     name="Asset Configuration",
 *     description="إدارة إعدادات العهد"
 * )
 */
class AssetConfigurationController extends Controller
{
    use ApiResponseTrait;

    protected $permissionService;
    protected $assetConfigService; // Service property

    public function __construct(SimplePermissionService $permissionService, AssetConfigurationService $assetConfigService)
    {
        $this->permissionService = $permissionService;
        $this->assetConfigService = $assetConfigService; // Inject Service
    }

    /**
     * @OA\Get(
     *     path="/api/assets/categories",
     *     summary="جلب قائمة فئات العهد",
     *     tags={"Asset Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب الفئات بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب الفئات بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="constants_id", type="integer", example=1),
     *                     @OA\Property(property="category_name", type="string", example="Laptops"),
     *                     @OA\Property(property="created_at", type="string", example="2024-01-01 10:00:00")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function indexCategories(): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $search = request('search');

            $categories = $this->assetConfigService->getCategories($effectiveCompanyId, $search);

            if(!$categories){
                Log::error('AssetConfigurationController::indexCategories failed', [
                    'message' => 'الفئات غير موجودة',
                    'company_id' => $effectiveCompanyId,
                    'search' => $search
                ]);
                return $this->errorResponse('الفئات غير موجودة', 404);
            }

            Log::info('AssetConfigurationController::indexCategories success', [
                'message' => 'تم جلب الفئات بنجاح',
                'company_id' => $effectiveCompanyId,
                'search' => $search
            ]);
            return $this->successResponse($categories, 'تم جلب الفئات بنجاح');
        } catch (\Exception $e) {
            Log::error('AssetConfigurationController::indexCategories failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب الفئات', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/assets/categories",
     *     summary="إضافة فئة عهد جديدة",
     *     tags={"Asset Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Laptops")
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إضافة الفئة بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function storeCategory(StoreAssetConfigurationRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $category = $this->assetConfigService->createCategory($effectiveCompanyId, $request->name);

            if(!$category){
                Log::error('AssetConfigurationController::storeCategory failed', [
                    'message' => 'الفئة غير موجودة',
                    'company_id' => $effectiveCompanyId,
                    'name' => $request->name
                ]);
                return $this->errorResponse('الفئة غير موجودة', 404);
            }

            Log::info('AssetConfigurationController::storeCategory success', [
                'message' => 'تم إضافة الفئة بنجاح',
                'company_id' => $effectiveCompanyId,
                'name' => $request->name
            ]);
            return $this->successResponse($category, 'تم إضافة الفئة بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('AssetConfigurationController::storeCategory failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'name' => $request->name
            ]);
            return $this->errorResponse('حدث خطأ أثناء إضافة الفئة', 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/assets/categories/{id}",
     *     summary="تعديل فئة عهد",
     *     tags={"Asset Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Updated Category")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تعديل الفئة بنجاح"),
     *     @OA\Response(response=404, description="الفئة غير موجودة"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateCategory(StoreAssetConfigurationRequest $request, int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $category = $this->assetConfigService->updateCategory($effectiveCompanyId, $id, $request->name);

            if (!$category) {
                Log::error('AssetConfigurationController::updateCategory failed', [
                    'message' => 'الفئة غير موجودة',
                    'company_id' => $effectiveCompanyId,
                    'id' => $id,
                    'name' => $request->name
                ]);
                return $this->errorResponse('الفئة غير موجودة', 404);
            }

            Log::info('AssetConfigurationController::updateCategory success', [
                'message' => 'تم تعديل الفئة بنجاح',
                'company_id' => $effectiveCompanyId,
                'id' => $id,
                'name' => $request->name
            ]);
            return $this->successResponse($category, 'تم تعديل الفئة بنجاح');
        } catch (\Exception $e) {
            Log::error('AssetConfigurationController::updateCategory failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'id' => $id,
                'name' => $request->name
            ]);
            return $this->errorResponse('حدث خطأ أثناء تعديل الفئة', 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/assets/categories/{id}",
     *     summary="حذف فئة عهد",
     *     tags={"Asset Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="الفئة غير موجودة"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroyCategory(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $deleted = $this->assetConfigService->deleteCategory($effectiveCompanyId, $id);

            if (!$deleted) {
                Log::error('AssetConfigurationController::destroyCategory failed', [
                    'message' => 'الفئة غير موجودة',
                    'company_id' => $effectiveCompanyId,
                    'id' => $id
                ]);
                return $this->errorResponse('الفئة غير موجودة', 404);
            }

            Log::info('AssetConfigurationController::destroyCategory success', [
                'message' => 'تم حذف الفئة بنجاح',
                'company_id' => $effectiveCompanyId,
                'id' => $id
            ]);
            return $this->successResponse(null, 'تم حذف الفئة بنجاح');
        } catch (\Exception $e) {
            Log::error('AssetConfigurationController::destroyCategory failed', [
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء حذف الفئة',
                'user_id' => Auth::id(),
                'id' => $id
            ]);
            return $this->errorResponse('حدث خطأ أثناء حذف الفئة', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/assets/brands",
     *     summary="جلب قائمة العلامات التجارية للعهد",
     *     tags={"Asset Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب العلامات التجارية بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب العلامات التجارية بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="constants_id", type="integer", example=1),
     *                     @OA\Property(property="category_name", type="string", example="Dell"),
     *                     @OA\Property(property="created_at", type="string", example="2024-01-01 10:00:00")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function indexBrands(): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $search = request('search');

            $brands = $this->assetConfigService->getBrands($effectiveCompanyId, $search);

            if(!$brands){
                Log::error('AssetConfigurationController::indexBrands failed', [
                    'message' => 'العلامات التجارية غير موجودة',
                    'company_id' => $effectiveCompanyId,
                    'search' => $search
                ]);
                return $this->errorResponse('العلامات التجارية غير موجودة', 404);
            }

            Log::info('AssetConfigurationController::indexBrands success', [
                'message' => 'تم جلب العلامات التجارية بنجاح',
                'company_id' => $effectiveCompanyId,
                'search' => $search
            ]);
            return $this->successResponse($brands, 'تم جلب العلامات التجارية بنجاح');
        } catch (\Exception $e) {
            Log::error('AssetConfigurationController::indexBrands failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'message' => 'حدث خطأ أثناء جلب العلامات التجارية'
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب العلامات التجارية', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/assets/brands",
     *     summary="إضافة علامة تجارية جديدة للعهد",
     *     tags={"Asset Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Dell")
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إضافة العلامة التجارية بنجاح"),
     *     @OA\Response(response=400, description="خطأ في الطلب"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function storeBrand(StoreAssetConfigurationRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $brand = $this->assetConfigService->createBrand($effectiveCompanyId, $request->name);

            if(!$brand){
                Log::error('AssetConfigurationController::storeBrand failed', [
                    'message' => 'العلامة التجارية غير موجودة',
                    'company_id' => $effectiveCompanyId,
                    'name' => $request->name
                ]);
                return $this->errorResponse('العلامة التجارية غير موجودة', 404);
            }

            Log::info('AssetConfigurationController::storeBrand success', [
                'message' => 'تم إضافة العلامة التجارية بنجاح',
                'company_id' => $effectiveCompanyId,
                'name' => $request->name
            ]);
            return $this->successResponse($brand, 'تم إضافة العلامة التجارية بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('AssetConfigurationController::storeBrand failed', [
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء إضافة العلامة التجارية',
                'user_id' => Auth::id(),
                'name' => $request->name
            ]);
            return $this->errorResponse('حدث خطأ أثناء إضافة العلامة التجارية', 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/assets/brands/{id}",
     *     summary="تعديل علامة تجارية",
     *     tags={"Asset Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Updated Brand")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تعديل العلامة التجارية بنجاح"),
     *     @OA\Response(response=404, description="العلامة التجارية غير موجودة"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateBrand(StoreAssetConfigurationRequest $request, int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $brand = $this->assetConfigService->updateBrand($effectiveCompanyId, $id, $request->name);

            if (!$brand) {
                Log::error('AssetConfigurationController::updateBrand failed', [
                    'message' => 'العلامة التجارية غير موجودة',
                    'company_id' => $effectiveCompanyId,
                    'id' => $id,
                    'name' => $request->name
                ]);
                return $this->errorResponse('العلامة التجارية غير موجودة', 404);
            }

            Log::info('AssetConfigurationController::updateBrand success', [
                'message' => 'تم تعديل العلامة التجارية بنجاح',
                'company_id' => $effectiveCompanyId,
                'id' => $id,
                'name' => $request->name
            ]);
            return $this->successResponse($brand, 'تم تعديل العلامة التجارية بنجاح');
        } catch (\Exception $e) {
            Log::error('AssetConfigurationController::updateBrand failed', [
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء تعديل العلامة التجارية',
                'user_id' => Auth::id(),
                'id' => $id,
                'name' => $request->name
            ]);
            return $this->errorResponse('حدث خطأ أثناء تعديل العلامة التجارية', 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/assets/brands/{id}",
     *     summary="حذف علامة تجارية",
     *     tags={"Asset Configuration"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="العلامة التجارية غير موجودة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroyBrand(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $deleted = $this->assetConfigService->deleteBrand($effectiveCompanyId, $id);

            if (!$deleted) {
                Log::error('AssetConfigurationController::destroyBrand failed', [
                    'message' => 'العلامة التجارية غير موجودة',
                    'company_id' => $effectiveCompanyId,
                    'id' => $id
                ]);
                return $this->errorResponse('العلامة التجارية غير موجودة', 404);
            }

            Log::info('AssetConfigurationController::destroyBrand success', [
                'message' => 'تم حذف العلامة التجارية بنجاح',
                'company_id' => $effectiveCompanyId,
                'id' => $id
            ]);
            return $this->successResponse(null, 'تم حذف العلامة التجارية بنجاح');
        } catch (\Exception $e) {
            Log::error('AssetConfigurationController::destroyBrand failed', [
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء حذف العلامة التجارية',
                'user_id' => Auth::id(),
                'id' => $id
            ]);
            return $this->errorResponse('حدث خطأ أثناء حذف العلامة التجارية', 500);
        }
    }
}
