<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Promotion\CreatePromotionDTO;
use App\DTOs\Promotion\UpdatePromotionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\StorePromotionRequest;
use App\Http\Requests\Promotion\UpdatePromotionRequest;
use App\Services\PromotionService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Promotion Management",
 *     description="إدارة الترقيات"
 * )
 */
class PromotionController extends Controller
{
    use ApiResponseTrait;

    protected $promotionService;
    protected $permissionService;

    public function __construct(
        PromotionService $promotionService,
        SimplePermissionService $permissionService
    ) {
        $this->promotionService = $promotionService;
        $this->permissionService = $permissionService;
    }

    /**
     * @OA\Get(
     *     path="/api/promotions",
     *     summary="عرض قائمة الترقيات",
     *     tags={"Promotion Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $filters = $request->only(['employee_id', 'search']);
            $perPage = (int) $request->input('per_page', 15);

            $promotions = $this->promotionService->getPromotions($effectiveCompanyId, $filters, $perPage);

            Log::info('Promotions fetched successfully', [
                'user_id' => Auth::id(),
                'promotions' => $promotions,
                'message' => 'تم جلب البيانات بنجاح'
            ]);
            return $this->successResponse($promotions, 'تم جلب البيانات بنجاح', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching promotions: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء جلب البيانات'
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب البيانات', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/promotions",
     *     summary="إضافة ترقية جديدة",
     *     tags={"Promotion Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "promotion_title", "promotion_date", "new_designation_id", "new_department_id", "new_salary"},
     *             @OA\Property(property="employee_id", type="integer", example=767),
     *             @OA\Property(property="promotion_title", type="string", example="Promotion Title"),
     *             @OA\Property(property="promotion_date", type="string", format="date", example="2026-01-01"),
     *             @OA\Property(property="new_designation_id", type="integer", example=1),
     *             @OA\Property(property="new_department_id", type="integer", example=1),
     *             @OA\Property(property="new_salary", type="number", format="float", example=1000.00),
     *             @OA\Property(property="description", type="string", example="Description"),
     *             @OA\Property(property="notify_send_to", type="array", @OA\Items(type="integer"), example={767, 768})
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إضافة الترقية بنجاح"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(StorePromotionRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = CreatePromotionDTO::fromRequest($request->validated(), $effectiveCompanyId, Auth::id());

            $promotion = $this->promotionService->createPromotion($dto);

            Log::info('Promotion created successfully', [
                'user_id' => Auth::id(),
                'promotion' => $promotion,
                'message' => 'تم إضافة الترقية بنجاح'
            ]);
            return $this->successResponse($promotion, 'تم إضافة الترقية بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('Error creating promotion: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء إضافة الترقية'
            ]);
            return $this->errorResponse('حدث خطأ أثناء إضافة الترقية', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/promotions/{id}",
     *     summary="عرض تفاصيل الترقية",
     *     tags={"Promotion Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب التفاصيل بنجاح"),
     *     @OA\Response(response=404, description="الترقية غير موجودة"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $promotion = $this->promotionService->getPromotion($id, $effectiveCompanyId);

            if (!$promotion) {
                return $this->errorResponse('الترقية غير موجودة', 404);
            }

            Log::info('Promotion shown successfully', [
                'user_id' => Auth::id(),
                'promotion_id' => $id,
            ]);
            return $this->successResponse($promotion, 'تم جلب التفاصيل بنجاح', 200);
        } catch (\Exception $e) {
            Log::error('Error showing promotion: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'promotion_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب التفاصيل', 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/promotions/{id}",
     *     summary="تعديل ترقية",
     *     tags={"Promotion Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="promotion_title", type="string", example="promotion_title"),
     *             @OA\Property(property="promotion_date", type="string", format="date", example="2026-01-01"),
     *             @OA\Property(property="new_designation_id", type="integer", example=1),
     *             @OA\Property(property="new_department_id", type="integer", example=1),
     *             @OA\Property(property="new_salary", type="number", format="float", example=1000.00),
     *             @OA\Property(property="description", type="string", example="Description"),
     *             @OA\Property(property="notify_send_to", type="array", @OA\Items(type="integer"), example={767, 768}),
     *             @OA\Property(property="status", type="string", description="Pending, Approved, Rejected", example="Pending")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تعديل الترقية بنجاح"),
     *     @OA\Response(response=404, description="الترقية غير موجودة"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(UpdatePromotionRequest $request, int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = UpdatePromotionDTO::fromRequest($request->validated());

            $promotion = $this->promotionService->updatePromotion($id, $dto, $effectiveCompanyId);

            Log::info('Promotion updated successfully', [
                'user_id' => Auth::id(),
                'promotion_id' => $id,
                'promotion' => $promotion,
            ]);
            return $this->successResponse($promotion, 'تم تعديل الترقية بنجاح', 200);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            $message = $e->getMessage() ?: 'حدث خطأ أثناء تعديل الترقية';

            if ($code >= 400 && $code < 500) {
                return $this->errorResponse($message, $code);
            }

            Log::error('Error updating promotion: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'promotion_id' => $id,
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('حدث خطأ أثناء تعديل الترقية', 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/promotions/{id}",
     *     summary="حذف ترقية",
     *     tags={"Promotion Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="الترقية غير موجودة"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $this->promotionService->deletePromotion($id, $effectiveCompanyId);

            Log::info('Promotion deleted successfully', [
                'user_id' => Auth::id(),
                'promotion_id' => $id,
            ]);
            return $this->successResponse(null, 'تم الحذف بنجاح', 200);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            $message = $e->getMessage() ?: 'حدث خطأ أثناء الحذف';

            if ($code >= 400 && $code < 500) {
                return $this->errorResponse($message, $code);
            }

            Log::error('Error deleting promotion: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'promotion_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('حدث خطأ أثناء الحذف', 500);
        }
    }
}
