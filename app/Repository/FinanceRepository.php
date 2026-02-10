<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTOs\Finance\CreateAccountDTO;
use App\DTOs\Finance\CreateCategoryDTO;
use Illuminate\Support\Facades\DB;
use App\DTOs\Finance\CreateTransactionDTO;
use App\Models\ErpConstant;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Repository\Interface\FinanceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class FinanceRepository implements FinanceRepositoryInterface
{
    // =============================================
    // =========== ACCOUNTS (Company) =============
    // =============================================

    public function getAllAccounts(int $companyId, ?int $perPage = null, ?string $search = null, ?int $page = null, array $filters = []): mixed
    {
        $query = FinanceAccount::where('company_id', $companyId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('account_name', 'LIKE', "%{$search}%")
                    ->orWhere('account_number', 'LIKE', "%{$search}%")
                    ->orWhere('bank_branch', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('account_name');

        return $perPage ? $query->paginate($perPage, ['*'], 'page', $page) : $query->get();
    }

    public function getAccountById(int $id, int $companyId): ?FinanceAccount
    {
        return FinanceAccount::where('account_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    public function createAccount(CreateAccountDTO $dto): FinanceAccount
    {
        return FinanceAccount::create($dto->toArray());
    }

    public function updateAccount(FinanceAccount $account, array $data): FinanceAccount
    {
        $account->update($data);
        return $account->refresh();
    }

    public function deleteAccount(FinanceAccount $account): bool
    {
        return $account->delete();
    }

    public function updateAccountBalance(int $accountId, float $amount): bool
    {
        $affected = FinanceAccount::where('account_id', $accountId)
            ->update([
                'account_balance' => DB::raw("account_balance + ({$amount})")
            ]);

        return $affected > 0;
    }

    // =============================================
    // =========== EMPLOYEE ACCOUNTS ==============
    // =============================================

    public function createEmployeeAccount(array $data): \App\Models\EmployeeAccount
    {
        return \App\Models\EmployeeAccount::create($data);
    }

    public function getEmployeeAccountById(int $id, int $companyId): ?\App\Models\EmployeeAccount
    {
        return \App\Models\EmployeeAccount::where('account_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    public function getAllEmployeeAccounts(int $companyId, ?int $perPage = null, ?string $search = null, ?int $page = null, array $filters = []): mixed
    {
        $query = \App\Models\EmployeeAccount::where('company_id', $companyId);

        if ($search) {
            $query->where('account_name', 'LIKE', "%{$search}%");
        }

        $query->orderBy('account_name');

        return $perPage ? $query->paginate($perPage, ['*'], 'page', $page) : $query->get();
    }

    public function updateEmployeeAccount(\App\Models\EmployeeAccount $account, array $data): \App\Models\EmployeeAccount
    {
        $account->update($data);
        return $account->refresh();
    }

    public function deleteEmployeeAccount(\App\Models\EmployeeAccount $account): bool
    {
        return $account->delete();
    }

    // =============================================
    // =========== CATEGORIES (ErpConstants) ========
    // =============================================

    public function getAllCategories(int $companyId, ?string $type = null, ?int $perPage = null, ?string $search = null, ?int $page = null, array $filters = []): mixed
    {
        $query = ErpConstant::forCompany($companyId);

        if ($type) {
            $query->ofType($type);
        } else {
            $query->whereIn('type', ['expense_type', 'income_type']);
        }

        if ($search) {
            $query->where('category_name', 'LIKE', "%{$search}%");
        }

        $query->orderBy('category_name');

        return $perPage ? $query->paginate($perPage, ['constants_id', 'type', 'category_name'], 'page', $page) : $query->get(['constants_id', 'type', 'category_name']);
    }

    public function getPaymentMethods(?int $perPage = null, ?string $search = null, ?int $page = null): mixed
    {
        $query = ErpConstant::where('type', 'payment_method');

        if ($search) {
            $query->where('category_name', 'LIKE', "%{$search}%");
        }

        $query->orderBy('category_name');

        return $perPage ? $query->paginate($perPage, ['constants_id', 'type', 'category_name'], 'page', $page) : $query->get(['constants_id', 'type', 'category_name']);
    }

    public function createCategory(CreateCategoryDTO $dto): ErpConstant
    {
        $type = in_array($dto->type, ['expense', 'expense_type']) ? 'expense_type' : 'income_type';

        return ErpConstant::create([
            'company_id' => $dto->companyId,
            'type' => $type,
            'category_name' => $dto->name,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function updateCategory(ErpConstant $category, array $data): ErpConstant
    {
        $category->update($data);
        return $category->refresh();
    }

    public function deleteCategory(ErpConstant $category): bool
    {
        return (bool) $category->delete();
    }

    // =============================================
    // =========== TRANSACTIONS ====================
    // =============================================

    public function getTransactions(int $companyId, array $filters = [], ?int $perPage = null, ?string $search = null, ?int $page = null): mixed
    {
        $query = FinanceTransaction::with(['account', 'category'])
            ->where('company_id', $companyId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                    ->orWhere('reference', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (!empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('transaction_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('transaction_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['dr_cr'])) {
            $query->where('dr_cr', $filters['dr_cr']);
        }

        $query->orderBy('transaction_date', 'desc')
            ->orderBy('transaction_id', 'desc');

        return $perPage ? $query->paginate($perPage, ['*'], 'page', $page) : $query->get();
    }

    public function createTransaction(CreateTransactionDTO $dto): FinanceTransaction
    {
        return FinanceTransaction::create($dto->toArray());
    }

    public function getTransactionById(int $id, int $companyId): ?FinanceTransaction
    {
        return FinanceTransaction::with(['account', 'category'])
            ->where('transaction_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    public function updateTransaction(FinanceTransaction $transaction, array $data): FinanceTransaction
    {
        $transaction->update($data);
        return $transaction;
    }

    public function deleteTransaction(FinanceTransaction $transaction): bool
    {
        return $transaction->delete();
    }

    public function getBalance(int $accountId): float
    {
        $account = FinanceAccount::find($accountId);
        return $account ? (float) $account->account_balance : 0.0;
    }
}
