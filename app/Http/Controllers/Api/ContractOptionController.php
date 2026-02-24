<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContractOption\ContractOptionIndexRequest;
use App\Http\Requests\ContractOption\StoreContractOptionRequest;
use App\Http\Requests\ContractOption\UpdateContractOptionRequest;
use App\Http\Resources\ContractOptionResource;
use App\Services\ContractOptionService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Contract Options",
 *     description="إدارة خيارات العقد (البدلات/العمولات/الخصومات النظامية/المدفوعات الأخرى)"
 * )
 */
class ContractOptionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ContractOptionService $contractOptionService,
        private readonly SimplePermissionService $permissionService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/contract-options/allowances",
     *     summary="قائمة البدلات",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", maxLength=200)),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function indexAllowances(ContractOptionIndexRequest $request): JsonResponse
    {
        return $this->indexByType($request, 'allowances');
    }

    /**
     * @OA\Get(
     *     path="/api/contract-options/allowances/{id}",
     *     summary="عرض بدل",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function showAllowance(int $id): JsonResponse
    {
        return $this->showByType($id, 'allowances');
    }

    /**
     * @OA\Post(
     *     path="/api/contract-options/allowances",
     *     summary="إنشاء بدل",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_fixed","option_title"},
     *             @OA\Property(property="contract_tax_option", type="integer", nullable=true, example=0),
     *             @OA\Property(property="is_fixed", type="integer", enum={0,1}, example=1),
     *             @OA\Property(property="option_title", type="string", example="بدل سكن"),
     *             @OA\Property(property="contract_amount", type="number", nullable=true, example=100)
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إنشاء العنصر بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function storeAllowance(StoreContractOptionRequest $request): JsonResponse
    {
        return $this->storeByType($request, 'allowances');
    }

    /**
     * @OA\Put(
     *     path="/api/contract-options/allowances/{id}",
     *     summary="تعديل بدل",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="contract_tax_option", type="integer", nullable=true, example=0),
     *             @OA\Property(property="is_fixed", type="integer", enum={0,1}, example=1),
     *             @OA\Property(property="option_title", type="string", example="بدل سكن"),
     *             @OA\Property(property="contract_amount", type="number", nullable=true, example=100)
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تحديث العنصر بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateAllowance(UpdateContractOptionRequest $request, int $id): JsonResponse
    {
        return $this->updateByType($request, $id, 'allowances');
    }

    /**
     * @OA\Delete(
     *     path="/api/contract-options/allowances/{id}",
     *     summary="حذف بدل",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف العنصر بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroyAllowance(int $id): JsonResponse
    {
        return $this->destroyByType($id, 'allowances');
    }

    /**
     * @OA\Get(
     *     path="/api/contract-options/commissions",
     *     summary="قائمة العمولات",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", maxLength=200)),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function indexCommissions(ContractOptionIndexRequest $request): JsonResponse
    {
        return $this->indexByType($request, 'commissions');
    }

    /**
     * @OA\Get(
     *     path="/api/contract-options/commissions/{id}",
     *     summary="عرض عمولة",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function showCommission(int $id): JsonResponse
    {
        return $this->showByType($id, 'commissions');
    }

    /**
     * @OA\Post(
     *     path="/api/contract-options/commissions",
     *     summary="إنشاء عمولة",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_fixed","option_title"},
     *             @OA\Property(property="contract_tax_option", type="integer", nullable=true, example=0),
     *             @OA\Property(property="is_fixed", type="integer", enum={0,1}, example=1),
     *             @OA\Property(property="option_title", type="string", example="عمولة"),
     *             @OA\Property(property="contract_amount", type="number", nullable=true, example=100)
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إنشاء العنصر بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function storeCommission(StoreContractOptionRequest $request): JsonResponse
    {
        return $this->storeByType($request, 'commissions');
    }

    /**
     * @OA\Put(
     *     path="/api/contract-options/commissions/{id}",
     *     summary="تعديل عمولة",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="contract_tax_option", type="integer", nullable=true, example=0),
     *             @OA\Property(property="is_fixed", type="integer", enum={0,1}, example=1),
     *             @OA\Property(property="option_title", type="string", example="عمولة"),
     *             @OA\Property(property="contract_amount", type="number", nullable=true, example=100)
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تحديث العنصر بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateCommission(UpdateContractOptionRequest $request, int $id): JsonResponse
    {
        return $this->updateByType($request, $id, 'commissions');
    }

    /**
     * @OA\Delete(
     *     path="/api/contract-options/commissions/{id}",
     *     summary="حذف عمولة",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف العنصر بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroyCommission(int $id): JsonResponse
    {
        return $this->destroyByType($id, 'commissions');
    }

    /**
     * @OA\Get(
     *     path="/api/contract-options/statutory",
     *     summary="قائمة الاستقطاعات",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", maxLength=200)),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function indexStatutory(ContractOptionIndexRequest $request): JsonResponse
    {
        return $this->indexByType($request, 'statutory');
    }

    /**
     * @OA\Get(
     *     path="/api/contract-options/statutory/{id}",
     *     summary="عرض استقطاع",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function showStatutory(int $id): JsonResponse
    {
        return $this->showByType($id, 'statutory');
    }

    /**
     * @OA\Post(
     *     path="/api/contract-options/statutory",
     *     summary="إنشاء استقطاع",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_fixed","option_title"},
     *             @OA\Property(property="contract_tax_option", type="integer", nullable=true, example=0),
     *             @OA\Property(property="is_fixed", type="integer", enum={0,1}, example=1),
     *             @OA\Property(property="option_title", type="string", example="تأمينات"),
     *             @OA\Property(property="contract_amount", type="number", nullable=true, example=100)
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إنشاء العنصر بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function storeStatutory(StoreContractOptionRequest $request): JsonResponse
    {
        return $this->storeByType($request, 'statutory');
    }

    /**
     * @OA\Put(
     *     path="/api/contract-options/statutory/{id}",
     *     summary="تعديل الاستقطاع",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="contract_tax_option", type="integer", nullable=true, example=0),
     *             @OA\Property(property="is_fixed", type="integer", enum={0,1}, example=1),
     *             @OA\Property(property="option_title", type="string", example="تأمينات"),
     *             @OA\Property(property="contract_amount", type="number", nullable=true, example=100)
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تحديث العنصر بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateStatutory(UpdateContractOptionRequest $request, int $id): JsonResponse
    {
        return $this->updateByType($request, $id, 'statutory');
    }

    /**
     * @OA\Delete(
     *     path="/api/contract-options/statutory/{id}",
     *     summary="حذف الاستقطاع",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف العنصر بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroyStatutory(int $id): JsonResponse
    {
        return $this->destroyByType($id, 'statutory');
    }

    /**
     * @OA\Get(
     *     path="/api/contract-options/other-payments",
     *     summary="قائمة التعويضات",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", maxLength=200)),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function indexOtherPayments(ContractOptionIndexRequest $request): JsonResponse
    {
        return $this->indexByType($request, 'other_payments');
    }

    /**
     * @OA\Get(
     *     path="/api/contract-options/other-payments/{id}",
     *     summary="عرض التعويض",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function showOtherPayment(int $id): JsonResponse
    {
        return $this->showByType($id, 'other_payments');
    }

    /**
     * @OA\Post(
     *     path="/api/contract-options/other-payments",
     *     summary="إنشاء التعويض",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_fixed","option_title"},
     *             @OA\Property(property="contract_tax_option", type="integer", nullable=true, example=0),
     *             @OA\Property(property="is_fixed", type="integer", enum={0,1}, example=1),
     *             @OA\Property(property="option_title", type="string", example="حافز"),
     *             @OA\Property(property="contract_amount", type="number", nullable=true, example=100)
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إنشاء العنصر بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function storeOtherPayment(StoreContractOptionRequest $request): JsonResponse
    {
        return $this->storeByType($request, 'other_payments');
    }

    /**
     * @OA\Put(
     *     path="/api/contract-options/other-payments/{id}",
     *     summary="تعديل التعويض",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="contract_tax_option", type="integer", nullable=true, example=0),
     *             @OA\Property(property="is_fixed", type="integer", enum={0,1}, example=1),
     *             @OA\Property(property="option_title", type="string", example="حافز"),
     *             @OA\Property(property="contract_amount", type="number", nullable=true, example=100)
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تحديث العنصر بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function updateOtherPayment(UpdateContractOptionRequest $request, int $id): JsonResponse
    {
        return $this->updateByType($request, $id, 'other_payments');
    }

    /**
     * @OA\Delete(
     *     path="/api/contract-options/other-payments/{id}",
     *     summary="حذف التعويض",
     *     tags={"Contract Options"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف العنصر بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroyOtherPayment(int $id): JsonResponse
    {
        return $this->destroyByType($id, 'other_payments');
    }

    private function indexByType(ContractOptionIndexRequest $request, string $type): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $options = $this->contractOptionService->getAllForCompanyByType($companyId, $type, $request->validated());

            Log::info('ContractOptionController::index success', [
                'user_id' => $user->user_id,
                'company_id' => $companyId,
                'type' => $type,
            ]);

            return $this->successResponse(ContractOptionResource::collection($options), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('ContractOptionController::index failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'type' => $type,
            ]);
            return $this->handleException($e, 'ContractOptionController::index');
        }
    }

    private function showByType(int $id, string $type): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $option = $this->contractOptionService->getByIdForCompanyByType($companyId, $id, $type);

            if (!$option) {
                return $this->notFoundResponse('العنصر غير موجود');
            }

            return $this->successResponse(new ContractOptionResource($option), 'تم جلب البيانات بنجاح');
        } catch (\Exception $e) {
            Log::error('ContractOptionController::show failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'id' => $id,
                'type' => $type,
            ]);
            return $this->handleException($e, 'ContractOptionController::show');
        }
    }

    private function storeByType(StoreContractOptionRequest $request, string $type): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $dto = \App\DTOs\ContractOption\StoreContractOptionDTO::fromRequest($request->validated(), $companyId, $user->user_id, $type);
            $option = $this->contractOptionService->store($dto);

            Log::info('ContractOptionController::store success', [
                'user_id' => $user->user_id,
                'company_id' => $companyId,
                'type' => $type,
                'contract_option_id' => $option->contract_option_id,
            ]);

            return $this->successResponse(new ContractOptionResource($option), 'تم إنشاء العنصر بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('ContractOptionController::store failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'type' => $type,
            ]);
            return $this->handleException($e, 'ContractOptionController::store');
        }
    }

    private function updateByType(UpdateContractOptionRequest $request, int $id, string $type): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $dto = \App\DTOs\ContractOption\UpdateContractOptionDTO::fromRequest($request->validated(), $type);

            $option = $this->contractOptionService->updateForCompanyByType($companyId, $id, $dto);

            if (!$option) {
                return $this->notFoundResponse('العنصر غير موجود');
            }

            Log::info('ContractOptionController::update success', [
                'user_id' => $user->user_id,
                'company_id' => $companyId,
                'type' => $type,
                'contract_option_id' => $id,
            ]);

            return $this->successResponse(new ContractOptionResource($option), 'تم تحديث العنصر بنجاح');
        } catch (\Exception $e) {
            Log::error('ContractOptionController::update failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'type' => $type,
                'contract_option_id' => $id,
            ]);
            return $this->handleException($e, 'ContractOptionController::update');
        }
    }

    private function destroyByType(int $id, string $type): JsonResponse
    {
        $user = Auth::user();

        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $deleted = $this->contractOptionService->deleteForCompanyByType($companyId, $id, $type);

            if (!$deleted) {
                return $this->notFoundResponse('العنصر غير موجود');
            }

            Log::info('ContractOptionController::destroy success', [
                'user_id' => $user->user_id,
                'company_id' => $companyId,
                'type' => $type,
                'contract_option_id' => $id,
            ]);

            return $this->successResponse(null, 'تم حذف العنصر بنجاح');
        } catch (\Exception $e) {
            Log::error('ContractOptionController::destroy failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->user_id,
                'type' => $type,
                'contract_option_id' => $id,
            ]);
            return $this->handleException($e, 'ContractOptionController::destroy');
        }
    }
}
