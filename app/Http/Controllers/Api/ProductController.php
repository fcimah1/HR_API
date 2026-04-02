<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BarcodeTypeEnum;
use App\Enums\ProductRatingEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Requests\Product\CreateProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\UpdateProductRatingRequest;
use App\Http\Requests\Product\ProductSearchRequest;
use App\DTOs\Product\ProductFilterDTO;
use App\Services\ProductService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Inventory (Products)",
 *     description="إدارة منتجات المخزون"
 * )
 */
class ProductController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected readonly ProductService $productService,
        protected readonly SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/inventory/products",
     *     operationId="getProducts",
     *     summary="عرض قائمة المنتجات",
     *     tags={"Inventory (Products)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="product_name", in="query", @OA\Schema(type="string"), description="البحث باسم المنتج"),
     *     @OA\Parameter(name="warehouse_id", in="query", @OA\Schema(type="integer"), description="فلترة بالمخزن"),
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer"), description="فلترة بالفئة"),
     *     @OA\Parameter(name="out_of_stock", in="query", @OA\Schema(type="boolean"), description="المنتجات غير المتوفرة فقط"),
     *     @OA\Parameter(name="expired", in="query", @OA\Schema(type="boolean"), description="المنتجات منتهية الصلاحية فقط"),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer"), description="رقم الصفحة"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="عدد العناصر في الصفحة"),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=500, description="خطأ في جلب بيانات المنتجات"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function index(ProductSearchRequest $request): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $dto = ProductFilterDTO::fromRequest($request->all(), $companyId);
            $products = $this->productService->getProducts($dto);

            Log::info('تم جلب قائمة المنتجات بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'filters' => $request->all()
            ]);

            if ($dto->paginate) {
                return $this->paginatedResponse($products, 'تم جلب البيانات بنجاح', ProductResource::class);
            }

            return $this->successResponse(ProductResource::collection($products), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في جلب قائمة المنتجات', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'filters' => $request->all(),
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في جلب قائمة المنتجات');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/inventory/products",
     *     operationId="storeProduct",
     *     summary="إضافة منتج جديد",
     *     tags={"Inventory (Products)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(ref="#/components/schemas/CreateProductRequest")
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إضافة المنتج بنجاح"),
     *     @OA\Response(response=422, description="بيانات غير صحيحة"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في إضافة المنتج")
     * )
     */
    public function store(CreateProductRequest $request): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $data = $request->validated();
            $data['company_id'] = $companyId;
            $data['added_by'] = Auth::id();

            $imageFile = $request->file('product_image');
            $product = $this->productService->createProduct($data, $imageFile);

            Log::info('تم إضافة منتج جديد بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'product_id' => $product->product_id
            ]);

            return $this->successResponse(new ProductResource($product), 'تم إضافة المنتج بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('خطأ في إضافة المنتج', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في إضافة المنتج');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/inventory/products/{id}",
     *     operationId="getProductDetails",
     *     summary="عرض تفاصيل منتج",
     *     tags={"Inventory (Products)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=404, description="المنتج غير موجود"),
     *     @OA\Response(response=500, description="خطأ في جلب بيانات المنتج"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $product = $this->productService->getProductById($id, $companyId);

            if (!$product) {
                Log::warning('محاولة جلب منتج غير موجود', [
                    'product_id' => $id,
                    'user_id' => Auth::id(),
                    'company_id' => $companyId
                ]);
                return $this->notFoundResponse('المنتج غير موجود');
            }

            return $this->successResponse(new ProductResource($product), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في جلب تفاصيل المنتج', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في جلب تفاصيل المنتج');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/inventory/products/{id}",
     *     operationId="updateProduct",
     *     summary="تحديث منتج",
     *     description="يستخدم POST مع إضافة _method=PUT لتحديث البيانات بما فيها الصور",
     *     tags={"Inventory (Products)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(ref="#/components/schemas/UpdateProductRequest")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم التحديث بنجاح"),
     *     @OA\Response(response=404, description="المنتج غير موجود"),
     *     @OA\Response(response=500, description="خطأ في تحديث بيانات المنتج"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $data = $request->validated();
            $imageFile = $request->file('product_image');

            $product = $this->productService->updateProduct($id, $companyId, $data, $imageFile);

            if (!$product) {
                Log::warning('محاولة تحديث منتج غير موجود', [
                    'product_id' => $id,
                    'user_id' => Auth::id(),
                    'company_id' => $companyId
                ]);
                return $this->notFoundResponse('المنتج غير موجود');
            }

            Log::info('تم تحديث بيانات المنتج بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'product_id' => $id
            ]);

            return $this->successResponse(new ProductResource($product), 'تم تحديث المنتج بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في تحديث المنتج', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في تحديث المنتج');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/inventory/products/{id}",
     *     operationId="deleteProduct",
     *     summary="حذف منتج",
     *     tags={"Inventory (Products)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="المنتج غير موجود"),
     *     @OA\Response(response=500, description="خطأ في حذف المنتج"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $success = $this->productService->deleteProduct($id, $companyId);

            if (!$success) {
                Log::warning('محاولة حذف منتج غير موجود', [
                    'product_id' => $id,
                    'user_id' => Auth::id(),
                    'company_id' => $companyId
                ]);
                return $this->notFoundResponse('المنتج غير موجود');
            }

            Log::info('تم حذف المنتج بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'product_id' => $id
            ]);

            return $this->successResponse(null, 'تم حذف المنتج بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في حذف المنتج', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في حذف المنتج');
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/inventory/products/{id}/rating",
     *     operationId="updateProductRating",
     *     summary="تحديث تقييم المنتج",
     *     tags={"Inventory (Products)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="product_rating", in="query", required=true, @OA\Schema(type="integer", enum={1, 2, 3, 4, 5}), description="تقييم المنتج من 1 إلى 5"),
     *     @OA\Response(response=200, description="تم التحديث بنجاح"),
     *     @OA\Response(response=404, description="المنتج غير موجود"),
     *     @OA\Response(response=500, description="خطأ في تحديث تقييم المنتج"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function updateRating(UpdateProductRatingRequest $request, int $id): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

        try {
            $product = $this->productService->updateRating($id, $companyId, (int)$request->product_rating);

            if (!$product) {
                Log::warning('محاولة تحديث تقييم منتج غير موجود', [
                    'product_id' => $id,
                    'user_id' => Auth::id(),
                    'company_id' => $companyId
                ]);
                return $this->notFoundResponse('المنتج غير موجود');
            }

            Log::info('تم تحديث التقييم بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'product_id' => $id,
                'rating' => $request->product_rating
            ]);

            return $this->successResponse(new ProductResource($product), 'تم تحديث التقييم بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في تحديث تقييم المنتج', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في تحديث تقييم المنتج');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/inventory/products/out-of-stock",
     *     operationId="getOutOfStockProducts",
     *     summary="عرض قائمة المنتجات غير المتوفرة",
     *     tags={"Inventory (Products)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginate", in="query", required=false, @OA\Schema(type="boolean", default=true)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=500, description="خطأ في جلب بيانات المنتجات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function getOutOfStockProducts(ProductSearchRequest $request): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $data = $request->validated();
            $data['out_of_stock'] = true;
            $dto = ProductFilterDTO::fromRequest($data, $companyId);
            $products = $this->productService->getProducts($dto);

            if ($dto->paginate) {
                return $this->paginatedResponse($products, 'تم جلب البيانات بنجاح', ProductResource::class);
            }

            return $this->successResponse(ProductResource::collection($products), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'خطأ في جلب قائمة المنتجات غير المتوفرة ');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/inventory/products/expired",
     *     operationId="getExpiredProducts",
     *     summary="عرض قائمة المنتجات منتهية الصلاحية",
     *     tags={"Inventory (Products)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginate", in="query", required=false, @OA\Schema(type="boolean", default=true)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=500, description="خطأ في جلب بيانات المنتجات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function getExpiredProducts(ProductSearchRequest $request): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $data = $request->validated();
            $data['expired'] = true;
            $dto = ProductFilterDTO::fromRequest($data, $companyId);
            $products = $this->productService->getProducts($dto);

            if ($dto->paginate) {
                return $this->paginatedResponse($products, 'تم جلب البيانات بنجاح', ProductResource::class);
            }

            return $this->successResponse(ProductResource::collection($products), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'خطأ في جلب قائمة المنتجات منتهية الصلاحية');
        }
    }
    /**
     * @OA\Get(
     *     path="/api/inventory/products/constants",
     *     operationId="getProductConstants",
     *     summary="جلب ثوابت المنتجات (أنواع الباركود والتقييمات)",
     *     tags={"Inventory (Products)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب البيانات بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب البيانات بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="barcode_types", type="array", @OA\Items(
     *                     @OA\Property(property="value", type="string", example="CODE128"),
     *                     @OA\Property(property="label", type="string", example="CODE128")
     *                 )),
     *                 @OA\Property(property="ratings", type="array", @OA\Items(
     *                     @OA\Property(property="value", type="integer", example=5),
     *                     @OA\Property(property="label", type="string", example="ممتاز")
     *                 ))
     *             )
     *         )
     *     )
     * )
     */
    public function getConstants(): JsonResponse
    {
        return $this->successResponse([
            'barcode_types' => BarcodeTypeEnum::toArray(),
            'ratings' => ProductRatingEnum::toArray()
        ], 'تم جلب البيانات بنجاح');
    }
}
