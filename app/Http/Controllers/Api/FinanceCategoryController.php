<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Finance\CreateCategoryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreCategoryRequest;
use App\Http\Requests\Finance\UpdateCategoryRequest;
use App\Models\ErpConstant;
use App\Services\FinanceService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="Finance Categories", description="إدارة فئات المصروفات والإيرادات")
 */
class FinanceCategoryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly FinanceService $financeService,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/finance/categories/expense",
     *     operationId="getFinanceExpenseCategories",
     *     summary="عرض فئات المصروفات",
     *     tags={"Finance Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(response=200, description="تم جلب فئات المصروفات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function expenseTypes(Request $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $perPage = (int) $request->query('per_page', 15);
            $search = $request->query('search');
            $page = (int) $request->query('page', 1);
            $categories = $this->financeService->getExpenseCategories($companyId, $perPage, $search, $page);

            Log::info("Finance Expense Categories Fetched", [
                'company_id' => $companyId,
                'categories_count' => $categories->count(),
                'user_id' => $request->user()->user_id,
                'message' => 'تم جلب فئات المصروفات بنجاح'
            ]);
            return $this->paginatedResponse($categories, 'تم جلب فئات المصروفات بنجاح');
        } catch (\Exception $e) {
            Log::error("Finance Expense Categories Fetch Failed", [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'user_id' => $request->user()->user_id,
                'message' => 'فشل جلب فئات المصروفات'
            ]);
            return $this->handleException($e, 'FinanceCategoryController@expenseTypes');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/finance/categories/expense",
     *     operationId="storeFinanceExpenseCategory",
     *     summary="إنشاء فئة مصروفات جديدة",
     *     tags={"Finance Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/StoreCategoryRequest")),
     *     @OA\Response(response=201, description="تم إنشاء فئة المصروفات بنجاح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات")
     * )
     */
    public function storeExpense(StoreCategoryRequest $request): JsonResponse
    {
        return $this->storeCategory($request, 'expense_type');
    }


    /**
     * @OA\Put(
     *     path="/api/finance/categories/expense/{id}",
     *     operationId="updateFinanceExpenseCategory",
     *     summary="تحديث فئة مصروفات",
     *     tags={"Finance Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateCategoryRequest")),
     *     @OA\Response(response=200, description="تم تحديث فئة المصروفات بنجاح"),
     *     @OA\Response(response=404, description="الفئة غير موجودة")
     * )
     */
    public function updateExpense(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        return $this->updateCategory($request, $id, 'expense_type');
    }


    /**
     * @OA\Delete(
     *     path="/api/finance/categories/expense/{id}",
     *     operationId="deleteFinanceExpenseCategory",
     *     summary="حذف فئة مصروفات",
     *     tags={"Finance Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف فئة المصروفات بنجاح"),
     *     @OA\Response(response=404, description="الفئة غير موجودة")
     * )
     */
    public function destroyExpense(Request $request, int $id): JsonResponse
    {
        return $this->destroyCategory($request, $id, 'expense_type');
    }


    /**
     * @OA\Get(
     *     path="/api/finance/categories/income",
     *     operationId="getFinanceIncomeCategories",
     *     summary="عرض فئات الإيداعات",
     *     tags={"Finance Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(response=200, description="تم جلب فئات الإيداعات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */

    
    public function incomeTypes(Request $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $perPage = (int) $request->query('per_page', 15);
            $page = (int) $request->query('page', 1);
            $search = $request->query('search');
            $categories = $this->financeService->getIncomeCategories($companyId, $perPage, $search, $page);

            Log::info("Finance Income Categories Fetched", [
                'company_id' => $companyId,
                'categories_count' => $categories->count(),
                'user_id' => $request->user()->user_id,
                'message' => 'تم جلب فئات الإيداعات بنجاح'
            ]);
            return $this->paginatedResponse($categories, 'تم جلب فئات الإيداعات بنجاح');
        } catch (\Exception $e) {
            Log::error("Finance Income Categories Fetch Failed", [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'user_id' => $request->user()->user_id,
                'message' => 'فشل جلب فئات الإيداعات'
            ]);
            return $this->handleException($e, 'FinanceCategoryController@incomeTypes');
        }
    }
   

    /**
     * @OA\Post(
     *     path="/api/finance/categories/income",
     *     operationId="storeFinanceIncomeCategory",
     *     summary="إنشاء فئة إيداعات جديدة",
     *     tags={"Finance Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/StoreCategoryRequest")),
     *     @OA\Response(response=201, description="تم إنشاء فئة الإيداعات بنجاح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات")
     * )
     */
    public function storeIncome(StoreCategoryRequest $request): JsonResponse
    {
        return $this->storeCategory($request, 'income_type');
    }

    private function storeCategory(StoreCategoryRequest $request, string $type): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            // Force the type from the route
            $request->merge(['type' => $type]);

            $dto = CreateCategoryDTO::fromRequest($request, $companyId);
            $category = $this->financeService->createCategory($dto);

            Log::info("Finance " . ucfirst($type) . " Category Created", [
                'category_id' => $category->constants_id,
                'name' => $category->category_name,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id
            ]);

            return $this->successResponse($category, 'تم إنشاء الفئة بنجاح', 201);
        } catch (\Exception $e) {
            return $this->handleException($e, 'FinanceCategoryController@store' . ucfirst($type));
        }
    }

    /**
     * @OA\Put(
     *     path="/api/finance/categories/income/{id}",
     *     operationId="updateFinanceIncomeCategory",
     *     summary="تحديث فئة إيداعات",
     *     tags={"Finance Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateCategoryRequest")),
     *     @OA\Response(response=200, description="تم تحديث فئة الإيداعات بنجاح"),
     *     @OA\Response(response=404, description="الفئة غير موجودة")
     * )
     */
    public function updateIncome(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        return $this->updateCategory($request, $id, 'income_type');
    }

    private function updateCategory(UpdateCategoryRequest $request, int $id, string $dbType): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $category = ErpConstant::where('constants_id', $id)
                ->where('company_id', $companyId)
                ->where('type', $dbType)
                ->first();

            if (!$category) {
                return $this->notFoundResponse('الفئة غير موجودة');
            }

            $updated = $this->financeService->updateCategory($category, [
                'category_name' => $request->validated('name', $category->category_name),
            ]);

            return $this->successResponse($updated, 'تم تحديث الفئة بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'FinanceCategoryController@update');
        }
    }


    /**
     * @OA\Delete(
     *     path="/api/finance/categories/income/{id}",
     *     operationId="deleteFinanceIncomeCategory",
     *     summary="حذف فئة إيداعات",
     *     tags={"Finance Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف فئة الإيداعات بنجاح"),
     *     @OA\Response(response=404, description="الفئة غير موجودة")
     * )
     */
    public function destroyIncome(Request $request, int $id): JsonResponse
    {
        return $this->destroyCategory($request, $id, 'income_type');
    }

    private function destroyCategory(Request $request, int $id, string $dbType): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $category = ErpConstant::where('constants_id', $id)
                ->where('company_id', $companyId)
                ->where('type', $dbType)
                ->first();

            if (!$category) {
                return $this->notFoundResponse('الفئة غير موجودة');
            }

            $this->financeService->deleteCategory($category);

            return $this->successResponse(null, 'تم حذف الفئة بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'FinanceCategoryController@destroy');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/finance/payment-methods",
     *     operationId="getFinancePaymentMethods",
     *     summary="عرض طرق الدفع",
     *     tags={"Finance Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(response=200, description="تم جلب طرق الدفع بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 15);
            $search = $request->query('search');
            $page = (int) $request->query('page', 1);
            $methods = $this->financeService->getPaymentMethods($perPage, $search, $page);

            return $this->paginatedResponse($methods, 'تم جلب طرق الدفع بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'FinanceCategoryController@paymentMethods');
        }
    }

}
