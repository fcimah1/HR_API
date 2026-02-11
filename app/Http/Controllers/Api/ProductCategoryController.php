<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\ProductCategoryResource;
use App\Http\Requests\Inventory\ProductCategoryRequest;
use App\DTOs\Inventory\ProductCategoryDTO;
use App\DTOs\Inventory\ProductCategoryFilterDTO;
use App\Services\ProductCategoryService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Inventory (Product Categories)",
 *     description="إدارة فئات المنتجات"
 * )
 */
class ProductCategoryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected readonly ProductCategoryService $categoryService,
        protected readonly SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/inventory/product-categories",
     *     operationId="getProductCategories",
     *     summary="عرض فئات المنتجات",
     *     tags={"Inventory (Product Categories)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="البحث بالاسم"),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer"), description="رقم الصفحة"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="عدد العناصر في الصفحة"),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=500, description="خطأ في جلب بيانات فئات المنتجات"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $filters = ProductCategoryFilterDTO::fromRequest($request->all(), $companyId);
            $categories = $this->categoryService->getCategories($filters);

            Log::info('تم جلب قائمة فئات المنتجات بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'filters' => $request->all()
            ]);

            if ($filters->paginate) {
                return $this->paginatedResponse($categories, 'تم جلب البيانات بنجاح', ProductCategoryResource::class);
            }

            return $this->successResponse(ProductCategoryResource::collection($categories), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في جلب فئات المنتجات', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في جلب فئات المنتجات');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/inventory/product-categories",
     *     operationId="storeProductCategory",
     *     summary="إضافة فئة منتج",
     *     tags={"Inventory (Product Categories)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"category_name"},
     *         @OA\Property(property="category_name", description="اسم الفئة", example="Electronics", type="string")
     *     )),
     *     @OA\Response(response=201, description="تم الإضافة بنجاح"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function store(ProductCategoryRequest $request): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $dto = ProductCategoryDTO::fromRequest($request->validated(), $companyId);

            $category = $this->categoryService->createCategory($dto);

            Log::info('تم إضافة فئة المنتج بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'category_id' => $category->constants_id
            ]);

            return $this->successResponse(new ProductCategoryResource($category), 'تم إضافة فئة المنتج بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('خطأ في إضافة فئة المنتج', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في إضافة فئة المنتج');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/inventory/product-categories/{id}",
     *     operationId="getProductCategoryDetails",
     *     summary="عرض تفاصيل فئة المنتج",
     *     tags={"Inventory (Product Categories)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=404, description="الفئة غير موجودة"),
     *     @OA\Response(response=500, description="خطأ في جلب بيانات فئة المنتج"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $category = $this->categoryService->getCategoryById($id, $companyId);

            if (!$category) {
                Log::warning('محاولة جلب فئة منتج غير موجودة', [
                    'category_id' => $id,
                    'user_id' => Auth::id()
                ]);
                return $this->notFoundResponse('الفئة غير موجودة');
            }

            return $this->successResponse(new ProductCategoryResource($category), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في جلب بيانات فئة المنتج', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في جلب بيانات فئة المنتج');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/inventory/product-categories/{id}",
     *     operationId="updateProductCategory",
     *     summary="تحديث فئة منتج",
     *     tags={"Inventory (Product Categories)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"category_name"},
     *         @OA\Property(property="category_name", description="اسم الفئة", example="Electronics", type="string")
     *     )),
     *     @OA\Response(response=200, description="تم التحديث بنجاح"),
     *     @OA\Response(response=404, description="الفئة غير موجودة"),
     *     @OA\Response(response=500, description="خطأ في تحديث بيانات فئة المنتج"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function update(ProductCategoryRequest $request, int $id): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $dto = ProductCategoryDTO::fromRequest($request->validated(), $companyId);

            $category = $this->categoryService->updateCategory($id, $companyId, $dto);

            if (!$category) {
                Log::warning('محاولة تحديث فئة منتج غير موجودة', [
                    'category_id' => $id,
                    'user_id' => Auth::id()
                ]);
                return $this->notFoundResponse('الفئة غير موجودة');
            }

            Log::info('تم تحديث فئة المنتج بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'category_id' => $id
            ]);

            return $this->successResponse(new ProductCategoryResource($category), 'تم تحديث فئة المنتج بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في تحديث فئة المنتج', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'category_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في تحديث فئة المنتج');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/inventory/product-categories/{id}",
     *     operationId="deleteProductCategory",
     *     summary="حذف فئة منتج",
     *     tags={"Inventory (Product Categories)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="الفئة غير موجودة"),
     *     @OA\Response(response=500, description="خطأ في حذف فئة المنتج"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $success = $this->categoryService->deleteCategory($id, $companyId);

            if (!$success) {
                Log::warning('محاولة حذف فئة منتج غير موجودة', [
                    'category_id' => $id,
                    'user_id' => Auth::id()
                ]);
                return $this->notFoundResponse('الفئة غير موجودة');
            }

            Log::info('تم حذف فئة المنتج بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'category_id' => $id
            ]);

            return $this->successResponse(null, 'تم حذف الفئة بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في حذف الفئة', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'category_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في حذف الفئة');
        }
    }
}
