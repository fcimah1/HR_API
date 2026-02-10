<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Finance\CreateAccountDTO;
use App\DTOs\Finance\CreateCategoryDTO;
use App\DTOs\Finance\CreateTransactionDTO;
use App\DTOs\Finance\UpdateTransactionDTO;
use App\Models\ErpConstant;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Repository\Interface\FinanceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function __construct(
        private readonly FinanceRepositoryInterface $financeRepository
    ) {}

    // =============================================
    // =========== ACCOUNTS (Company) =============
    // =============================================

    public function getAllAccounts(int $companyId, ?int $perPage = null, ?string $search = null, ?int $page = null, array $filters = []): mixed
    {
        return $this->financeRepository->getAllAccounts($companyId, $perPage, $search, $page, $filters);
    }

    public function getAccountById(int $id, int $companyId): ?FinanceAccount
    {
        return $this->financeRepository->getAccountById($id, $companyId);
    }

    public function createAccount(CreateAccountDTO $dto): FinanceAccount
    {
        $account = $this->financeRepository->createAccount($dto);

        // الرصيد الافتتاحي
        if ($dto->accountOpeningBalance > 0) {
            $account->update(['account_balance' => $dto->accountOpeningBalance]);
        }

        return $account;
    }

    public function updateAccount(FinanceAccount $account, array $data): FinanceAccount
    {
        return $this->financeRepository->updateAccount($account, $data);
    }

    public function deleteAccount(FinanceAccount $account): bool
    {
        if ($account->transactions()->exists()) {
            throw new \RuntimeException('لا يمكن حذف حساب يحتوي على معاملات مالية');
        }

        return $this->financeRepository->deleteAccount($account);
    }

    // =============================================
    // =========== EMPLOYEE ACCOUNTS ==============
    // =============================================

    public function createEmployeeAccount(array $data): \App\Models\EmployeeAccount
    {
        return $this->financeRepository->createEmployeeAccount($data);
    }

    public function getEmployeeAccountById(int $id, int $companyId): ?\App\Models\EmployeeAccount
    {
        return $this->financeRepository->getEmployeeAccountById($id, $companyId);
    }

    public function getAllEmployeeAccounts(int $companyId, ?int $perPage = null, ?string $search = null, ?int $page = null, array $filters = []): mixed
    {
        return $this->financeRepository->getAllEmployeeAccounts($companyId, $perPage, $search, $page, $filters);
    }

    public function updateEmployeeAccount(\App\Models\EmployeeAccount $account, array $data): \App\Models\EmployeeAccount
    {
        return $this->financeRepository->updateEmployeeAccount($account, $data);
    }

    public function deleteEmployeeAccount(\App\Models\EmployeeAccount $account): bool
    {
        return $this->financeRepository->deleteEmployeeAccount($account);
    }

    // =============================================
    // =========== CATEGORIES (ErpConstants) ========
    // =============================================

    public function getExpenseCategories(int $companyId, ?int $perPage = null, ?string $search = null, ?int $page = null, array $filters = []): mixed
    {
        return $this->financeRepository->getAllCategories($companyId, 'expense_type', $perPage, $search, $page, $filters);
    }

    public function getIncomeCategories(int $companyId, ?int $perPage = null, ?string $search = null, ?int $page = null, array $filters = []): mixed
    {
        return $this->financeRepository->getAllCategories($companyId, 'income_type', $perPage, $search, $page, $filters);
    }

    public function getAllCategories(int $companyId, ?string $type = null, ?int $perPage = null, ?string $search = null, ?int $page = null, array $filters = []): mixed
    {
        return $this->financeRepository->getAllCategories($companyId, $type, $perPage, $search, $page, $filters);
    }

    public function getPaymentMethods(?int $perPage = null, ?string $search = null, ?int $page = null): mixed
    {
        return $this->financeRepository->getPaymentMethods($perPage, $search, $page);
    }

    public function createCategory(CreateCategoryDTO $dto): ErpConstant
    {
        return $this->financeRepository->createCategory($dto);
    }

    public function updateCategory(ErpConstant $category, array $data): ErpConstant
    {
        return $this->financeRepository->updateCategory($category, $data);
    }

    public function deleteCategory(ErpConstant $category): bool
    {
        return $this->financeRepository->deleteCategory($category);
    }

    // =============================================
    // =========== TRANSACTIONS ====================
    // =============================================

    /**
     * تسجيل إيداع - Deposit (cr = Credit to account)
     */
    public function recordDeposit(CreateTransactionDTO $dto): FinanceTransaction
    {
        return DB::transaction(function () use ($dto) {
            // 1. تسجيل المعاملة
            $transaction = $this->financeRepository->createTransaction($dto);

            // 2. تحديث رصيد الحساب (إضافة)
            $this->financeRepository->updateAccountBalance($dto->accountId, $dto->amount);

            return $transaction;
        });
    }

    /**
     * تسجيل مصروف - Expense (dr = Debit from account)
     */
    public function recordExpense(CreateTransactionDTO $dto): FinanceTransaction
    {
        return DB::transaction(function () use ($dto) {
            // التحقق من كفاية الرصيد
            $balance = $this->financeRepository->getBalance($dto->accountId);
            if ($balance < $dto->amount) {
                throw new \RuntimeException('رصيد الحساب غير كافٍ لتنفيذ هذه العملية');
            }

            // 1. تسجيل المعاملة
            $transaction = $this->financeRepository->createTransaction($dto);

            // 2. تحديث رصيد الحساب (خصم)
            $this->financeRepository->updateAccountBalance($dto->accountId, -$dto->amount);

            return $transaction;
        });
    }

    /**
     * تحويل بين حسابين - Transfer
     * ينشئ معاملتين: سحب من الحساب المصدر + إيداع في الحساب الهدف
     */
    public function processTransfer(CreateTransactionDTO $dto): array
    {
        if (!$dto->transferToAccountId) {
            throw new \RuntimeException('يجب تحديد الحساب الهدف للتحويل');
        }

        return DB::transaction(function () use ($dto) {
            // التحقق من كفاية الرصيد
            $balance = $this->financeRepository->getBalance($dto->accountId);
            if ($balance < $dto->amount) {
                throw new \RuntimeException('رصيد الحساب غير كافٍ لتنفيذ التحويل');
            }

            // 1. معاملة السحب من الحساب المصدر (Debit)
            $debitDto = new CreateTransactionDTO(
                companyId: $dto->companyId,
                accountId: $dto->accountId,
                staffId: $dto->staffId,
                transactionDate: $dto->transactionDate,
                transactionType: 'transfer',
                amount: $dto->amount,
                drCr: 'dr',
                entityId: $dto->entityId,
                entityType: $dto->entityType,
                entityCategoryId: $dto->entityCategoryId,
                description: $dto->description ?? 'تحويل صادر',
                paymentMethodId: $dto->paymentMethodId,
                reference: $dto->reference,
                attachmentFile: $dto->attachmentFile
            );
            $debitTransaction = $this->financeRepository->createTransaction($debitDto);

            // 2. معاملة الإيداع في الحساب الهدف (Credit)
            $creditDto = new CreateTransactionDTO(
                companyId: $dto->companyId,
                accountId: $dto->transferToAccountId,
                staffId: $dto->staffId,
                transactionDate: $dto->transactionDate,
                transactionType: 'transfer',
                amount: $dto->amount,
                drCr: 'cr',
                entityId: $dto->entityId,
                entityType: $dto->entityType,
                entityCategoryId: $dto->entityCategoryId,
                description: $dto->description ?? 'تحويل وارد',
                paymentMethodId: $dto->paymentMethodId,
                reference: $dto->reference,
                attachmentFile: $dto->attachmentFile
            );
            $creditTransaction = $this->financeRepository->createTransaction($creditDto);

            // 3. تحديث الأرصدة
            $this->financeRepository->updateAccountBalance($dto->accountId, -$dto->amount);
            $this->financeRepository->updateAccountBalance($dto->transferToAccountId, $dto->amount);

            return [
                'debit' => $debitTransaction,
                'credit' => $creditTransaction,
            ];
        });
    }

    public function getTransactions(int $companyId, array $filters = [], ?int $perPage = null, ?string $search = null, ?int $page = null): mixed
    {
        return $this->financeRepository->getTransactions($companyId, $filters, $perPage, $search, $page);
    }

    public function getTransactionById(int $id, int $companyId): ?FinanceTransaction
    {
        return $this->financeRepository->getTransactionById($id, $companyId);
    }

    /**
     * كشف حساب - Account Statement
     */
    public function getAccountStatement(int $accountId, int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $account = $this->financeRepository->getAccountById($accountId, $companyId);
        if (!$account) {
            throw new \RuntimeException('الحساب غير موجود');
        }

        $filters = ['account_id' => $accountId];
        if ($fromDate) $filters['from_date'] = $fromDate;
        if ($toDate) $filters['to_date'] = $toDate;

        $transactions = $this->financeRepository->getTransactions($companyId, $filters);

        return [
            'account' => $account,
            'transactions' => $transactions,
            'current_balance' => (float) $account->account_balance,
        ];
    }

    
    /**
     * تحديث معاملة مالية - Update Transaction
     * يعكس تأثير المعاملة القديمة ويطبق الجديدة
     */
    public function updateTransaction(UpdateTransactionDTO $dto, int $companyId): FinanceTransaction
    {
        return DB::transaction(function () use ($dto, $companyId) {
            $transaction = $this->financeRepository->getTransactionById($dto->transactionId, $companyId);
            if (!$transaction) {
                throw new \RuntimeException('المعاملة غير موجودة');
            }

            // 1. عكس التأثير القديم على الرصيد
            if ($transaction->dr_cr === 'cr') {
                // كانت إيداع -> نطرح المبلغ القديم من الرصيد
                $this->financeRepository->updateAccountBalance($transaction->account_id, -$transaction->amount);
            } else {
                // كانت مصروف -> نضيف المبلغ القديم للرصيد
                $this->financeRepository->updateAccountBalance($transaction->account_id, (float) $transaction->amount);
            }

            // 2. تطبيق التأثير الجديد
            if ($transaction->dr_cr === 'cr') {
                $this->financeRepository->updateAccountBalance($dto->accountId, $dto->amount);
            } else {
                // تحقق من الرصيد الجديد إذا تغير الحساب أو المبلغ
                $newBalance = $this->financeRepository->getBalance($dto->accountId);
                if ($newBalance < $dto->amount) {
                    throw new \RuntimeException('رصيد الحساب غير كافٍ لتعديل هذه العملية');
                }
                $this->financeRepository->updateAccountBalance($dto->accountId, -$dto->amount);
            }

            // 3. تحديث السجل
            return $this->financeRepository->updateTransaction($transaction, $dto->toArray());
        });
    }

    /**
     * حذف معاملة مالية - Delete Transaction
     * يعكس تأثير المعاملة على الرصيد قبل الحذف
     */
    public function deleteTransaction(int $id, int $companyId): bool
    {
        return DB::transaction(function () use ($id, $companyId) {
            $transaction = $this->financeRepository->getTransactionById($id, $companyId);
            if (!$transaction) {
                return false;
            }

            // عكس التأثير على الرصيد
            if ($transaction->dr_cr === 'cr') {
                $this->financeRepository->updateAccountBalance($transaction->account_id, -$transaction->amount);
            } else {
                $this->financeRepository->updateAccountBalance($transaction->account_id, (float) $transaction->amount);
            }

            return $this->financeRepository->deleteTransaction($transaction);
        });
    }
}
