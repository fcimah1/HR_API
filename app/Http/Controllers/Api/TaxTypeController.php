<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\TaxTypeResource;
use App\Http\Requests\Inventory\TaxTypeRequest;
use App\DTOs\Inventory\TaxTypeDTO;
use App\DTOs\Inventory\TaxTypeFilterDTO;
use App\Services\TaxTypeService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Inventory (Tax Types)",
 *     description="إدارة أنواع الضرائب للمخزون"
 * )
 */
class TaxTypeController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected readonly TaxTypeService $taxTypeService,
        protected readonly SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/inventory/tax-types",
     *     operationId="getTaxTypes",
     *     summary="عرض أنواع الضرائب",
     *     tags={"Inventory (Tax Types)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="البحث بالاسم"),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer"), description="رقم الصفحة"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="عدد العناصر في الصفحة"),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=500, description="خطأ في جلب بيانات أنواع الضرائب"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $filters = TaxTypeFilterDTO::fromRequest($request->all(), $companyId);
            $taxes = $this->taxTypeService->getTaxTypes($filters);

            Log::info('تم جلب قائمة أنواع الضرائب بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'filters' => $request->all()
            ]);

            if ($filters->paginate) {
                return $this->paginatedResponse($taxes, 'تم جلب البيانات بنجاح', TaxTypeResource::class);
            }

            return $this->successResponse(TaxTypeResource::collection($taxes), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في جلب أنواع الضرائب', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في جلب أنواع الضرائب');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/inventory/tax-types",
     *     operationId="storeTaxType",
     *     summary="إضافة نوع ضريبة",
     *     tags={"Inventory (Tax Types)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"tax_name", "tax_rate", "tax_type"},
     *         @OA\Property(property="tax_name", example="VAT", description="اسم نوع الضريبة", type="string"),
     *         @OA\Property(property="tax_rate", example="15", description="معدل الضريبة", type="string"),
     *         @OA\Property(property="tax_type", example="percentage", description="نوع الضريبة", type="string", enum={"percentage", "fixed"})
     *     )),
     *     @OA\Response(response=201, description="تم الإضافة بنجاح"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function store(TaxTypeRequest $request): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $dto = TaxTypeDTO::fromRequest($request->validated(), $companyId);

            $tax = $this->taxTypeService->createTaxType($dto);

            Log::info('تم إضافة نوع الضريبة بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'tax_id' => $tax->constants_id
            ]);

            return $this->successResponse(new TaxTypeResource($tax), 'تم إضافة نوع الضريبة بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('خطأ في إضافة نوع الضريبة', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في إضافة نوع الضريبة');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/inventory/tax-types/{id}",
     *     operationId="getTaxTypeDetails",
     *     summary="عرض تفاصيل نوع الضريبة",
     *     tags={"Inventory (Tax Types)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=404, description="نوع الضريبة غير موجود"),
     *     @OA\Response(response=500, description="خطأ في جلب بيانات نوع الضريبة"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $tax = $this->taxTypeService->getTaxTypeById($id, $companyId);

            if (!$tax) {
                Log::warning('محاولة جلب نوع ضريبة غير موجود', [
                    'tax_id' => $id,
                    'user_id' => Auth::id(),
                    'company_id' => $companyId
                ]);
                return $this->notFoundResponse('نوع الضريبة غير موجود');
            }

            return $this->successResponse(new TaxTypeResource($tax), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في جلب بيانات نوع الضريبة', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في جلب بيانات نوع الضريبة');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/inventory/tax-types/{id}",
     *     operationId="updateTaxType",
     *     summary="تحديث نوع ضريبة",
     *     tags={"Inventory (Tax Types)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"tax_name", "tax_rate", "tax_type"},
     *         @OA\Property(property="tax_name", example="VAT", description="اسم نوع الضريبة", type="string"),
     *         @OA\Property(property="tax_rate", example="15", description="معدل الضريبة", type="string"),
     *         @OA\Property(property="tax_type", example="percentage", description="نوع الضريبة", type="string", enum={"percentage", "fixed"})
     *     )),
     *     @OA\Response(response=200, description="تم التحديث بنجاح"),
     *     @OA\Response(response=404, description="نوع الضريبة غير موجود"),
     *     @OA\Response(response=500, description="خطأ في تحديث نوع الضريبة"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function update(TaxTypeRequest $request, int $id): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $dto = TaxTypeDTO::fromRequest($request->validated(), $companyId);

            $tax = $this->taxTypeService->updateTaxType($id, $companyId, $dto);

            if (!$tax) {
                Log::warning('محاولة تحديث نوع ضريبة غير موجود', [
                    'tax_id' => $id,
                    'user_id' => Auth::id(),
                    'company_id' => $companyId
                ]);
                return $this->notFoundResponse('نوع الضريبة غير موجود');
            }

            Log::info('تم تحديث نوع الضريبة بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'tax_id' => $id
            ]);

            return $this->successResponse(new TaxTypeResource($tax), 'تم تحديث نوع الضريبة بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في تحديث نوع الضريبة', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في تحديث نوع الضريبة');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/inventory/tax-types/{id}",
     *     operationId="deleteTaxType",
     *     summary="حذف نوع ضريبة",
     *     tags={"Inventory (Tax Types)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="نوع الضريبة غير موجود"),
     *     @OA\Response(response=500, description="خطأ في حذف نوع الضريبة"),
     *     @OA\Response(response=422, description="خطأ في التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
        try {
            $success = $this->taxTypeService->deleteTaxType($id, $companyId);

            if (!$success) {
                Log::warning('محاولة حذف نوع ضريبة غير موجود', [
                    'tax_id' => $id,
                    'user_id' => Auth::id(),
                    'company_id' => $companyId
                ]);
                return $this->notFoundResponse('نوع الضريبة غير موجود');
            }

            Log::info('تم حذف نوع الضريبة بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'tax_id' => $id
            ]);

            return $this->successResponse(null, 'تم حذف نوع الضريبة بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في حذف نوع الضريبة', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في حذف نوع الضريبة');
        }
    }
}
