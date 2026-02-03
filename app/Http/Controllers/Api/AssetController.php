<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Services\AssetService;
use App\Services\SimplePermissionService;
use App\DTOs\Asset\CreateAssetDTO;
use App\DTOs\Asset\UpdateAssetDTO;
use App\DTOs\Asset\AssetFilterDTO;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Asset Management",
 *     description="إدارة العهد"
 * )
 */
class AssetController extends Controller
{
    use ApiResponseTrait;

    protected $assetService;
    protected $permissionService;

    public function __construct(
        AssetService $assetService,
        SimplePermissionService $permissionService
    ) {
        $this->assetService = $assetService;
        $this->permissionService = $permissionService;
    }

    /**
     * @OA\Get(
     *     path="/api/assets",
     *     summary="جلب قائمة العهد (Assets)",
     *     tags={"Asset Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="بحث بالاسم أو الكود أو الرقم التسلسلي"),
     *     @OA\Parameter(name="asset_status", in="query", @OA\Schema(type="string", enum={"working"}), description="حالة العهدة"),
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer"), description="تصفية حسب الفئة"),
     *     @OA\Parameter(name="brand_id", in="query", @OA\Schema(type="integer"), description="تصفية حسب العلامة التجارية"),
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer"), description="تصفية حسب الموظف"),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer"), description="رقم الصفحة"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="عدد العناصر في الصفحة"),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $filters = AssetFilterDTO::fromRequest($request);

            $assets = $this->assetService->getAssets($effectiveCompanyId, $filters);

            return $this->successResponse($assets, 'تم جلب قائمة العهد بنجاح');
        } catch (\Exception $e) {
            Log::error('AssetController::index failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب البيانات', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/assets",
     *     summary="إضافة عهدة جديدة",
     *     tags={"Asset Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "assets_category_id"},
     *                 @OA\Property(property="name", type="string", example="MacBook Pro"),
     *                 @OA\Property(property="assets_category_id", type="integer", example=1),
     *                 @OA\Property(property="brand_id", type="integer", example=2),
     *                 @OA\Property(property="employee_id", type="integer", example=101),
     *                 @OA\Property(property="company_asset_code", type="string", example="AST-001"),
     *                 @OA\Property(property="purchase_date", type="string", format="date", example="2024-01-01"),
     *                 @OA\Property(property="invoice_number", type="string", example="INV-2024-001"),
     *                 @OA\Property(property="serial_number", type="string", example="SN123456789"),
     *                 @OA\Property(property="warranty_end_date", type="string", format="date", example="2025-01-01"),
     *                 @OA\Property(property="is_working", type="boolean", example=true),
     *                 @OA\Property(property="asset_note", type="string", example="ملحوظات"),
     *                 @OA\Property(property="asset_image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إضافة العهدة بنجاح"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(StoreAssetRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = CreateAssetDTO::fromRequest($request->validated(), $effectiveCompanyId);

            $asset = $this->assetService->createAsset($dto);

            return $this->successResponse($asset, 'تم إضافة العهدة بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('AssetController::store failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return $this->errorResponse('حدث خطأ أثناء إضافة العهدة', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/assets/{id}",
     *     summary="جلب تفاصيل عهدة",
     *     tags={"Asset Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب التفاصيل بنجاح"),
     *     @OA\Response(response=404, description="العهدة غير موجودة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $asset = $this->assetService->getAsset($id, $effectiveCompanyId);

            if (!$asset) {
                return $this->errorResponse('العهدة غير موجودة', 404);
            }

            return $this->successResponse($asset, 'تم جلب التفاصيل بنجاح');
        } catch (\Exception $e) {
            Log::error('AssetController::show failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'id' => $id
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب التفاصيل', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/assets/{id}",
     *     summary="تحديث بيانات عهدة (method spoofing used for multipart: _method=PUT)",
     *     tags={"Asset Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="_method", type="string", example="PUT"),
     *                 @OA\Property(property="name", type="string", example="Laptop"),
     *                 @OA\Property(property="is_working", type="boolean", example=true),
     *                 @OA\Property(property="asset_image", type="string", format="binary"),
     *                 @OA\Property(property="employee_id", type="integer", example=767),
     *                 @OA\Property(property="assets_category_id", type="integer", example=325),
     *                 @OA\Property(property="brand_id", type="integer", example=326),
     *                 @OA\Property(property="company_asset_code", type="string", example="AST-001"),
     *                 @OA\Property(property="purchase_date", type="string", format="date", example="2024-01-01"),
     *                 @OA\Property(property="invoice_number", type="string", example="INV-2024-001"),
     *                 @OA\Property(property="serial_number", type="string", example="SN123456789"),
     *                 @OA\Property(property="warranty_end_date", type="string", format="date", example="2025-01-01"),
     *                 @OA\Property(property="asset_note", type="string", example="ملحوظات"),
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم التحديث بنجاح"),
     *     @OA\Response(response=404, description="العهدة غير موجودة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(UpdateAssetRequest $request, int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = UpdateAssetDTO::fromRequest($request->validated(), $id);

            $asset = $this->assetService->updateAsset($dto, $effectiveCompanyId);

            if (!$asset) {
                return $this->errorResponse('العهدة غير موجودة', 404);
            }

            return $this->successResponse($asset, 'تم تحديث العهدة بنجاح');
        } catch (\Exception $e) {
            Log::error('AssetController::update failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'id' => $id
            ]);
            return $this->errorResponse('حدث خطأ أثناء تحديث العهدة', 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/assets/{id}",
     *     summary="حذف عهدة",
     *     tags={"Asset Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="العهدة غير موجودة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $deleted = $this->assetService->deleteAsset($id, $effectiveCompanyId);

            if (!$deleted) {
                return $this->errorResponse('العهدة غير موجودة', 404);
            }

            return $this->successResponse(null, 'تم حذف العهدة بنجاح');
        } catch (\Exception $e) {
            Log::error('AssetController::destroy failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'id' => $id
            ]);
            return $this->errorResponse('حدث خطأ أثناء الحذف', 500);
        }
    }
}
