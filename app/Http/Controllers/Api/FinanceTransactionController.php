<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Finance\CreateTransactionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreDepositRequest;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Requests\Finance\StoreTransferRequest;
use App\Http\Requests\Finance\UpdateDepositRequest;
use App\Http\Requests\Finance\UpdateExpenseRequest;
use App\Services\FileUploadService;
use App\Services\FinanceService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="Finance Transactions", description="إدارة المعاملات المالية - عرض وتحويلات")
 * @OA\Tag(name="Finance Deposits", description="إدارة عمليات الإيداع")
 * @OA\Tag(name="Finance Expenses", description="إدارة عمليات المصروفات")
 */
class FinanceTransactionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly FinanceService $financeService,
        private readonly SimplePermissionService $permissionService,
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/finance/transactions",
     *     operationId="getFinanceTransactions",
     *     summary="عرض جميع المعاملات المالية",
     *     tags={"Finance Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15), description="عدد السجلات في الصفحة"),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1), description="رقم الصفحة"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="بحث في الوصف أو المرجع"),
     *     @OA\Parameter(name="account_id", in="query", @OA\Schema(type="integer"), description="تصفية حسب الحساب"),
     *     @OA\Parameter(name="transaction_type", in="query", @OA\Schema(type="string", enum={"income","expense","transfer"}), description="نوع المعاملة"),
     *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date"), description="من تاريخ"),
     *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date"), description="إلى تاريخ"),
     *     @OA\Parameter(name="dr_cr", in="query", @OA\Schema(type="string", enum={"dr","cr"}), description="مدين أو دائن"),
     *     @OA\Response(response=200, description="تم جلب المعاملات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());

            $filters = $request->only([
                'account_id',
                'transaction_type',
                'from_date',
                'to_date',
                'dr_cr',
            ]);

            $perPage = (int) $request->query('per_page', 15);
            $search = $request->query('search');
            $page = (int) $request->query('page', 1);

            $transactions = $this->financeService->getTransactions($companyId, $filters, $perPage, $search, $page);

            Log::info("Finance Transactions Fetched", [
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم جلب المعاملات بنجاح'
            ]);
            return $this->paginatedResponse($transactions, 'تم جلب المعاملات بنجاح');
        } catch (\Exception $e) {
            Log::error("Finance Transactions Fetch Failed", [
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل جلب المعاملات'
            ]);
            return $this->handleException($e, 'FinanceTransactionController@index');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/finance/deposits",
     *     operationId="getFinanceDeposits",
     *     summary="عرض سجل الإيداعات",
     *     tags={"Finance Deposits"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="بحث في الوصف أو المرجع"),
     *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="تم جلب الإيداعات بنجاح")
     * )
     */
    public function depositIndex(Request $request): JsonResponse
    {
        $request->merge(['transaction_type' => 'income']);
        return $this->index($request);
    }

    /**
     * @OA\Get(
     *     path="/api/finance/expenses",
     *     operationId="getFinanceExpenses",
     *     summary="عرض سجل المصروفات",
     *     tags={"Finance Expenses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="بحث في الوصف أو المرجع"),
     *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="تم جلب المصروفات بنجاح")
     * )
     */
    public function expenseIndex(Request $request): JsonResponse
    {
        $request->merge(['transaction_type' => 'expense']);
        return $this->index($request);
    }

    /**
     * @OA\Get(
     *     path="/api/finance/deposits/{id}",
     *     operationId="getFinanceDepositShow",
     *     summary="عرض تفاصيل إيداع محدد",
     *     tags={"Finance Deposits"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب تفاصيل الإيداع بنجاح"),
     *     @OA\Response(response=404, description="الإيداع غير موجود أو النوع غير مطابق")
     * )
     */
    public function showDeposit(Request $request, int $id): JsonResponse
    {
        return $this->show($request, $id, 'income');
    }

    /**
     * @OA\Get(
     *     path="/api/finance/expenses/{id}",
     *     operationId="getFinanceExpenseShow",
     *     summary="عرض تفاصيل مصروف محدد",
     *     tags={"Finance Expenses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب تفاصيل المصروف بنجاح"),
     *     @OA\Response(response=404, description="المصروف غير موجود أو النوع غير مطابق")
     * )
     */
    public function showExpense(Request $request, int $id): JsonResponse
    {
        return $this->show($request, $id, 'expense');
    }



    /**
     * @OA\Get(
     *     path="/api/finance/transactions/{id}",
     *     operationId="getFinanceTransactionShow",
     *     summary="عرض تفاصيل معاملة محدد",
     *     tags={"Finance Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب تفاصيل المعاملة بنجاح"),
     *     @OA\Response(response=404, description="المعاملة غير موجودة")
     * )
     */
    public function show(Request $request, int $id, ?string $expectedType = null): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $transaction = $this->financeService->getTransactionById($id, $companyId);

            if (!$transaction || ($expectedType && $transaction->transaction_type !== $expectedType)) {
                return $this->notFoundResponse('المعاملة غير موجودة');
            }

            return $this->successResponse($transaction, 'تم جلب المعاملة بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'FinanceTransactionController@show');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/finance/deposits",
     *     operationId="storeFinanceDeposit",
     *     summary="تسجيل إيداع (Income / Credit)",
     *     tags={"Finance Deposits"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(ref="#/components/schemas/StoreDepositRequest")
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم تسجيل الإيداع بنجاح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function storeDeposit(StoreDepositRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $staffId = $request->user()->user_id;

            $request->merge(['transaction_type' => 'income', 'dr_cr' => 'cr']);

            // Handle file upload
            if ($request->hasFile('attachment')) {
                $upload = $this->fileUploadService->uploadDocument($request->file('attachment'), $staffId, 'transactions', 'deposit');
                $request->merge(['attachment_file' => $upload['filename']]);
            }

            $dto = CreateTransactionDTO::fromRequest($request, $companyId, $staffId);

            $transaction = $this->financeService->recordDeposit($dto);

            Log::info("Finance Deposit Recorded", [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $dto->amount,
                'account_id' => $dto->accountId,
                'staff_id' => $staffId,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم تسجيل الإيداع بنجاح'
            ]);

            return $this->successResponse($transaction, 'تم تسجيل الإيداع بنجاح', 201);
        } catch (\Exception $e) {
            Log::error("Finance Deposit Failed", [
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل تسجيل الإيداع'
            ]);
            return $this->handleException($e, 'FinanceTransactionController@storeDeposit');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/finance/expenses",
     *     operationId="storeFinanceExpense",
     *     summary="تسجيل مصروف (Expense / Debit)",
     *     tags={"Finance Expenses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(ref="#/components/schemas/StoreExpenseRequest")
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم تسجيل المصروف بنجاح"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
     * )
     */
    public function storeExpense(StoreExpenseRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $staffId = $request->user()->user_id;

            $request->merge(['transaction_type' => 'expense', 'dr_cr' => 'dr']);

            // Handle file upload
            if ($request->hasFile('attachment')) {
                $upload = $this->fileUploadService->uploadDocument($request->file('attachment'), $staffId, 'transactions', 'expense');
                $request->merge(['attachment_file' => $upload['filename']]);
            }

            $dto = CreateTransactionDTO::fromRequest($request, $companyId, $staffId);

            // Double check if entity is an employee account that manager has access to
            if ($request->validated('entity_type') === 'employee_account') {
                $employeeAccountId = (int) $request->validated('entity_id');
                $account = $this->financeService->getEmployeeAccountById($employeeAccountId, $companyId);
                if ($account) {
                    $targetEmployee = \App\Models\User::find($account->user_id);
                    if ($targetEmployee && !$this->permissionService->canAccessEmployee($request->user(), $targetEmployee)) {
                        Log::warning("Unauthorized expense attempt for employee account", [
                            'requester_id' => $request->user()->user_id,
                            'target_account_id' => $employeeAccountId,
                            'target_employee_id' => $account->user_id,
                            'company_id' => $companyId,
                            'user_id' => $request->user()->user_id,
                            'message' => 'ليس لديك صلاحية صرف لمساب هذا الموظف بناءً على الهيكل الوظيفي'
                        ]);
                        return $this->forbiddenResponse('ليس لديك صلاحية صرف لمساب هذا الموظف بناءً على الهيكل الوظيفي');
                    }
                }
            }

            $transaction = $this->financeService->recordExpense($dto);

            Log::info("Finance Expense Recorded", [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $dto->amount,
                'account_id' => $dto->accountId,
                'staff_id' => $staffId,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم تسجيل المصروف بنجاح'
            ]);

            return $this->successResponse($transaction, 'تم تسجيل المصروف بنجاح', 201);
        } catch (\Exception $e) {
            Log::error("Finance Expense Failed", [
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل تسجيل المصروف'
            ]);
            return $this->handleException($e, 'FinanceTransactionController@storeExpense');
        }
    }

    // /**
    //  * @OA\Post(
    //  *     path="/api/finance/transfers",
    //  *     operationId="storeFinanceTransfer",
    //  *     summary="تحويل بين حسابين",
    //  *     tags={"Finance Transactions"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\MediaType(
    //  *             mediaType="multipart/form-data",
    //  *             @OA\Schema(ref="#/components/schemas/StoreTransferRequest")
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=201, description="تم التحويل بنجاح"),
    //  *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
    //  *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول")
    //  * )
    //  */
    public function transfer(StoreTransferRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $staffId = $request->user()->user_id;

            $request->merge(['transaction_type' => 'transfer', 'dr_cr' => 'dr']);

            // Handle file upload
            if ($request->hasFile('attachment')) {
                $upload = $this->fileUploadService->uploadDocument($request->file('attachment'), $staffId, 'transactions', 'transfer');
                $request->merge(['attachment_file' => $upload['filename']]);
            }

            $dto = CreateTransactionDTO::fromRequest($request, $companyId, $staffId);

            $result = $this->financeService->processTransfer($dto);

            Log::info("Finance Transfer Processed", [
                'from_account_id' => $dto->accountId,
                'to_account_id' => $dto->transferToAccountId,
                'amount' => $dto->amount,
                'staff_id' => $staffId,
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'message' => 'تم التحويل بنجاح'
            ]);

            return $this->successResponse($result, 'تم التحويل بنجاح', 201);
        } catch (\Exception $e) {
            Log::error("Finance Transfer Failed", [
                'company_id' => $companyId,
                'user_id' => $request->user()->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل التحويل'
            ]);
            return $this->handleException($e, 'FinanceTransactionController@transfer');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/finance/deposits/{id}",
     *     operationId="updateFinanceDeposit",
     *     summary="تعديل إيداع",
     *     tags={"Finance Deposits"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(ref="#/components/schemas/UpdateDepositRequest")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم التعديل بنجاح"),
     *     @OA\Response(response=404, description="المعاملة غير موجودة")
     * )
     */
    public function updateDeposit(UpdateDepositRequest $request, int $id): JsonResponse
    {
        return $this->update($request, $id);
    }

    /**
     * @OA\Delete(
     *     path="/api/finance/deposits/{id}",
     *     operationId="deleteFinanceDeposit",
     *     summary="حذف إيداع",
     *     tags={"Finance Deposits"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="المعاملة غير موجودة")
     * )
     */
    public function destroyDeposit(Request $request, int $id): JsonResponse
    {
        return $this->destroy($request, $id);
    }

    /**
     * @OA\Put(
     *     path="/api/finance/expenses/{id}",
     *     operationId="updateFinanceExpense",
     *     summary="تعديل مصروف",
     *     tags={"Finance Expenses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(ref="#/components/schemas/UpdateExpenseRequest")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم التعديل بنجاح"),
     *     @OA\Response(response=404, description="المعاملة غير موجودة")
     * )
     */
    public function updateExpense(UpdateExpenseRequest $request, int $id): JsonResponse
    {
        return $this->update($request, $id);
    }

    /**
     * @OA\Delete(
     *     path="/api/finance/expenses/{id}",
     *     operationId="deleteFinanceExpense",
     *     summary="حذف مصروف",
     *     tags={"Finance Expenses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="المعاملة غير موجودة")
     * )
     */
    public function destroyExpense(Request $request, int $id): JsonResponse
    {
        return $this->destroy($request, $id);
    }

    private function update(FormRequest $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $staffId = $request->user()->user_id;

            // Handle file upload during update
            if ($request->hasFile('attachment')) {
                $typeForFolder = $request instanceof UpdateDepositRequest ? 'deposit' : 'expense';
                $upload = $this->fileUploadService->uploadDocument($request->file('attachment'), $staffId, 'transactions', $typeForFolder);
                $request->merge(['attachment_file' => $upload['filename']]);
            }

            $dto = \App\DTOs\Finance\UpdateTransactionDTO::fromRequest($id, $request, $companyId, $staffId);
            $result = $this->financeService->updateTransaction($dto, $companyId);

            return $this->successResponse($result, 'تم تعديل المعاملة بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'FinanceTransactionController@update');
        }
    }

    private function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($request->user());
            $result = $this->financeService->deleteTransaction($id, $companyId);

            if (!$result) {
                return $this->errorResponse('المعاملة غير موجودة', 404);
            }

            return $this->successResponse(null, 'تم حذف المعاملة بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'FinanceTransactionController@destroy');
        }
    }
}
