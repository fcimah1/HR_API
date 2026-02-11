<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\CreateSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Requests\Supplier\SupplierSearchRequest;
use App\Http\Resources\SupplierResource;
use App\Services\SupplierService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use App\DTOs\Supplier\CreateSupplierDTO;
use App\DTOs\Supplier\UpdateSupplierDTO;
use App\DTOs\Supplier\SupplierFilterDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Inventory (Suppliers)",
 *     description="إدارة الموردين"
 * )
 */
class SupplierController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected SupplierService $supplierService,
        protected SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/inventory/suppliers",
     *     operationId="getSuppliersList",
     *     summary="عرض قائمة الموردين",
     *     tags={"Inventory (Suppliers)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="supplier_name", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="city", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean", default="true")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(SupplierSearchRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $filters = SupplierFilterDTO::fromRequest($request->validated(), $companyId);
            $suppliers = $this->supplierService->getSuppliers($filters);

            Log::info('تم جلب قائمة الموردين بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'filters' => $request->all()
            ]);

            if ($filters->paginate) {
                return $this->paginatedResponse($suppliers, 'تم جلب البيانات بنجاح', SupplierResource::class);
            }

            return $this->successResponse(SupplierResource::collection($suppliers), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في جلب قائمة الموردين', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->handleException($e, 'خطأ في جلب قائمة الموردين');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/inventory/suppliers",
     *     operationId="storeSupplier",
     *     summary="إضافة مورد جديد",
     *     tags={"Inventory (Suppliers)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CreateSupplierRequest")),
     *     @OA\Response(response=201, description="تم إضافة المورد بنجاح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CreateSupplierRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $userId = Auth::id();
            $dto = CreateSupplierDTO::fromRequest($request->validated(), $companyId, $userId);
            $supplier = $this->supplierService->createSupplier($dto);

            Log::info('تم إضافة مورد جديد بنجاح', [
                'user_id' => $userId,
                'supplier_id' => $supplier->supplier_id,
                'data' => $request->validated()
            ]);

            return $this->successResponse(new SupplierResource($supplier), 'تم إضافة المورد بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('خطأ في إضافة مورد جديد', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            return $this->handleException($e, 'خطأ في إضافة مورد جديد');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/inventory/suppliers/{id}",
     *     operationId="showSupplier",
     *     summary="عرض تفاصيل المورد",
     *     tags={"Inventory (Suppliers)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب التفاصيل بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $supplier = $this->supplierService->getSupplier($id, $companyId);

            if (!$supplier) {
                Log::warning('محاولة عرض مورد غير موجود', [
                    'user_id' => Auth::id(),
                    'supplier_id' => $id
                ]);
                return $this->notFoundResponse('المورد غير موجود');
            }

            Log::info('تم جلب تفاصيل المورد بنجاح', [
                'user_id' => Auth::id(),
                'supplier_id' => $id
            ]);

            return $this->successResponse(new SupplierResource($supplier), 'تم جلب التفاصيل بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في عرض تفاصيل المورد', [
                'user_id' => Auth::id(),
                'supplier_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في عرض تفاصيل المورد');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/inventory/suppliers/{id}",
     *     operationId="updateSupplier",
     *     summary="تحديث بيانات المورد",
     *     tags={"Inventory (Suppliers)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateSupplierRequest")),
     *     @OA\Response(response=200, description="تم التحديث بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(UpdateSupplierRequest $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = UpdateSupplierDTO::fromRequest($request->validated());
            $supplier = $this->supplierService->updateSupplier($id, $companyId, $dto);

            if (!$supplier) {
                Log::warning('محاولة تحديث مورد غير موجود', [
                    'user_id' => Auth::id(),
                    'supplier_id' => $id
                ]);
                return $this->notFoundResponse('المورد غير موجود');
            }

            Log::info('تم تحديث المورد بنجاح', [
                'user_id' => Auth::id(),
                'supplier_id' => $id,
                'data' => $request->validated()
            ]);

            return $this->successResponse(new SupplierResource($supplier), 'تم تحديث المورد بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في تحديث المورد', [
                'user_id' => Auth::id(),
                'supplier_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في تحديث المورد');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/inventory/suppliers/{id}",
     *     operationId="deleteSupplier",
     *     summary="حذف مورد",
     *     tags={"Inventory (Suppliers)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $deleted = $this->supplierService->deleteSupplier($id, $companyId);

            if (!$deleted) {
                Log::warning('محاولة حذف مورد غير موجود', [
                    'user_id' => Auth::id(),
                    'supplier_id' => $id
                ]);
                return $this->notFoundResponse('المورد غير موجود');
            }

            Log::info('تم حذف المورد بنجاح', [
                'user_id' => Auth::id(),
                'supplier_id' => $id
            ]);

            return $this->successResponse(null, 'تم حذف المورد بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في حذف المورد', [
                'user_id' => Auth::id(),
                'supplier_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في حذف المورد');
        }
    }
}
