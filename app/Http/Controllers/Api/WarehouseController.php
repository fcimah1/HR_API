<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\CreateWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Http\Requests\Warehouse\WarehouseSearchRequest;
use App\Http\Resources\WarehouseResource;
use App\Services\WarehouseService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use App\DTOs\Warehouse\CreateWarehouseDTO;
use App\DTOs\Warehouse\UpdateWarehouseDTO;
use App\DTOs\Warehouse\WarehouseFilterDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Inventory (Warehouses)",
 *     description="إدارة المستودعات"
 * )
 */
class WarehouseController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected WarehouseService $warehouseService,
        protected SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/inventory/warehouses",
     *     operationId="getWarehousesList",
     *     summary="عرض قائمة المستودعات",
     *     tags={"Inventory (Warehouses)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="warehouse_name", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="city", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean", default="true")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(WarehouseSearchRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $filters = WarehouseFilterDTO::fromRequest($request->validated(), $companyId);
            $warehouses = $this->warehouseService->getWarehouses($filters);

            Log::info('تم جلب قائمة المستودعات بنجاح', [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'filters' => $request->all()
            ]);

            if ($filters->paginate) {
                return $this->paginatedResponse($warehouses, 'تم جلب البيانات بنجاح', WarehouseResource::class);
            }

            return $this->successResponse(WarehouseResource::collection($warehouses), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في جلب قائمة المستودعات', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->handleException($e, 'خطأ في جلب قائمة المستودعات');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/inventory/warehouses",
     *     operationId="storeWarehouse",
     *     summary="إضافة مستودع جديد",
     *     tags={"Inventory (Warehouses)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CreateWarehouseRequest")),
     *     @OA\Response(response=201, description="تم إضافة المستودع بنجاح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CreateWarehouseRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $userId = Auth::id();
            $dto = CreateWarehouseDTO::fromRequest($request->validated(), $companyId, $userId);
            $warehouse = $this->warehouseService->createWarehouse($dto);

            Log::info('تم إضافة مستودع جديد بنجاح', [
                'user_id' => $userId,
                'warehouse_id' => $warehouse->warehouse_id,
                'data' => $request->validated()
            ]);

            return $this->successResponse(new WarehouseResource($warehouse), 'تم إضافة المستودع بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('خطأ في إضافة مستودع جديد', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            return $this->handleException($e, 'خطأ في إضافة مستودع جديد');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/inventory/warehouses/{id}",
     *     operationId="showWarehouse",
     *     summary="عرض تفاصيل المستودع",
     *     tags={"Inventory (Warehouses)"},
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
            $warehouse = $this->warehouseService->getWarehouse($id, $companyId);

            if (!$warehouse) {
                Log::warning('محاولة عرض مستودع غير موجود', [
                    'user_id' => Auth::id(),
                    'warehouse_id' => $id
                ]);
                return $this->notFoundResponse('المستودع غير موجود');
            }

            Log::info('تم جلب تفاصيل المستودع بنجاح', [
                'user_id' => Auth::id(),
                'warehouse_id' => $id
            ]);

            return $this->successResponse(new WarehouseResource($warehouse), 'تم جلب التفاصيل بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في عرض تفاصيل المستودع', [
                'user_id' => Auth::id(),
                'warehouse_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في عرض تفاصيل المستودع');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/inventory/warehouses/{id}",
     *     operationId="updateWarehouse",
     *     summary="تحديث بيانات المستودع",
     *     tags={"Inventory (Warehouses)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateWarehouseRequest")),
     *     @OA\Response(response=200, description="تم التحديث بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(UpdateWarehouseRequest $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = UpdateWarehouseDTO::fromRequest($request->validated());
            $warehouse = $this->warehouseService->updateWarehouse($id, $companyId, $dto);

            if (!$warehouse) {
                Log::warning('محاولة تحديث مستودع غير موجود', [
                    'user_id' => Auth::id(),
                    'warehouse_id' => $id
                ]);
                return $this->notFoundResponse('المستودع غير موجود');
            }

            Log::info('تم تحديث المستودع بنجاح', [
                'user_id' => Auth::id(),
                'warehouse_id' => $id,
                'data' => $request->validated()
            ]);

            return $this->successResponse(new WarehouseResource($warehouse), 'تم تحديث المستودع بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في تحديث المستودع', [
                'user_id' => Auth::id(),
                'warehouse_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في تحديث المستودع');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/inventory/warehouses/{id}",
     *     operationId="deleteWarehouse",
     *     summary="حذف مستودع",
     *     tags={"Inventory (Warehouses)"},
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
            $deleted = $this->warehouseService->deleteWarehouse($id, $companyId);

            if (!$deleted) {
                Log::warning('محاولة حذف مستودع غير موجود', [
                    'user_id' => Auth::id(),
                    'warehouse_id' => $id
                ]);
                return $this->notFoundResponse('المستودع غير موجود');
            }

            Log::info('تم حذف المستودع بنجاح', [
                'user_id' => Auth::id(),
                'warehouse_id' => $id
            ]);

            return $this->successResponse(null, 'تم حذف المستودع بنجاح');
        } catch (\Exception $e) {
            Log::error('خطأ في حذف المستودع', [
                'user_id' => Auth::id(),
                'warehouse_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->handleException($e, 'خطأ في حذف المستودع');
        }
    }
}
