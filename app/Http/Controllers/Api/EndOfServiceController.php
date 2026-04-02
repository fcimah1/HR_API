<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EndOfService\CalculateEndOfServiceRequest;
use App\Http\Requests\EndOfService\UpdateEndOfServiceRequest;
use App\Http\Resources\EndOfServiceResource;
use App\DTOs\EndOfService\EndOfServiceFilterDTO;
use App\DTOs\EndOfService\UpdateEndOfServiceDTO;
use App\Services\EndOfServiceService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="End of Service Calculations",
 *     description="إدارة حسابات نهاية الخدمة"
 * )
 */
class EndOfServiceController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly EndOfServiceService $endOfServiceService,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/end-of-service",
     *     operationId="getEndOfServiceList",
     *     summary="عرض قائمة حسابات نهاية الخدمة",
     *     tags={"End of Service Calculations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="termination_type", in="query", @OA\Schema(type="string", enum={"resignation", "termination", "end_of_contract"})),
     *     @OA\Parameter(name="is_approved", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean", default="true")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default="10")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $filters = EndOfServiceFilterDTO::fromRequest($request->all(), $companyId);
            $calculations = $this->endOfServiceService->getAllCalculations($filters);

            return $this->successResponse(
                EndOfServiceResource::collection($calculations),
                'تم جلب حسابات نهاية الخدمة بنجاح'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء جلب البيانات: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/end-of-service/calculate",
     *     operationId="calculateEndOfService",
     *     summary="حساب مستحقات نهاية الخدمة (معاينة بدون حفظ)",
     *     tags={"End of Service Calculations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CalculateEndOfServiceRequest")),
     *     @OA\Response(response=200, description="تم الحساب بنجاح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function calculate(CalculateEndOfServiceRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $result = $this->endOfServiceService->calculate(
                employeeId: (int)$request->employee_id,
                terminationDate: $request->termination_date,
                terminationType: $request->termination_type,
                includeLeave: $request->boolean('include_leave', true),
                companyId: $companyId
            );

            return $this->successResponse($result, 'تم حساب مستحقات نهاية الخدمة بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/end-of-service",
     *     operationId="storeEndOfService",
     *     summary="حساب وحفظ مستحقات نهاية الخدمة",
     *     tags={"End of Service Calculations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CalculateEndOfServiceRequest")),
     *     @OA\Response(response=201, description="تم الحساب والحفظ بنجاح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CalculateEndOfServiceRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            $calculation = $this->endOfServiceService->calculateAndSave(
                employeeId: (int)$request->employee_id,
                terminationDate: $request->termination_date,
                terminationType: $request->termination_type,
                includeLeave: $request->boolean('include_leave', true),
                companyId: $companyId,
                calculatedBy: Auth::id(),
                notes: $request->notes
            );

            return $this->successResponse(
                new EndOfServiceResource($calculation->load(['employee', 'calculator'])),
                'تم حساب وحفظ مستحقات نهاية الخدمة بنجاح',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/end-of-service/{id}",
     *     operationId="showEndOfService",
     *     summary="عرض تفاصيل حساب نهاية الخدمة",
     *     tags={"End of Service Calculations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب التفاصيل بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $calculation = $this->endOfServiceService->getCalculationById($id, $companyId);

            if (!$calculation) {
                return $this->errorResponse('الحساب غير موجود', 404);
            }

            return $this->successResponse(
                new EndOfServiceResource($calculation),
                'تم جلب تفاصيل الحساب بنجاح'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // /**
    //  * @OA\Put(
    //  *     path="/api/end-of-service/{id}",
    //  *     operationId="updateEndOfService",
    //  *     summary="تحديث حساب نهاية الخدمة",
    //  *     tags={"End of Service Calculations"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateEndOfServiceRequest")),
    //  *     @OA\Response(response=200, description="تم التحديث بنجاح"),
    //  *     @OA\Response(response=404, description="غير موجود"),
    //  *     @OA\Response(response=500, description="خطأ في الخادم")
    //  * )
    //  */
    // public function update(UpdateEndOfServiceRequest $request, int $id): JsonResponse
    // {
    //     try {
    //         $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
    //         $dto = UpdateEndOfServiceDTO::fromRequest($request->validated(), Auth::id());

    //         $calculation = $this->endOfServiceService->updateCalculation($id, $companyId, $dto);

    //         if (!$calculation) {
    //             return $this->errorResponse('الحساب غير موجود', 404);
    //         }

    //         return $this->successResponse(
    //             new EndOfServiceResource($calculation),
    //             'تم تحديث الحساب بنجاح'
    //         );
    //     } catch (\Exception $e) {
    //         return $this->errorResponse($e->getMessage(), 500);
    //     }
    // }

    // /**
    //  * @OA\Delete(
    //  *     path="/api/end-of-service/{id}",
    //  *     operationId="deleteEndOfService",
    //  *     summary="حذف حساب نهاية الخدمة",
    //  *     tags={"End of Service Calculations"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    //  *     @OA\Response(response=200, description="تم الحذف بنجاح"),
    //  *     @OA\Response(response=404, description="غير موجود"),
    //  *     @OA\Response(response=500, description="خطأ في الخادم")
    //  * )
    //  */
    // public function destroy(int $id): JsonResponse
    // {
    //     try {
    //         $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
    //         $deleted = $this->endOfServiceService->deleteCalculation($id, $companyId);

    //         if (!$deleted) {
    //             return $this->errorResponse('الحساب غير موجود', 404);
    //         }

    //         return $this->successResponse(null, 'تم حذف الحساب بنجاح');
    //     } catch (\Exception $e) {
    //         return $this->errorResponse($e->getMessage(), 500);
    //     }
    // }
}
