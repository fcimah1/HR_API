<?php

namespace App\Repository\Interface;

use App\DTOs\Finance\CreateAccountDTO;
use App\DTOs\Finance\CreateCategoryDTO;
use App\DTOs\Finance\CreateTransactionDTO;
use App\Models\ErpConstant;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use Illuminate\Database\Eloquent\Collection;

interface FinanceRepositoryInterface
{
    // Accounts
    public function getAllAccounts(int $companyId, ?int $perPage = null, ?string $search = null, ?int $page = null, array $filters = []): mixed;
    public function getAccountById(int $id, int $companyId): ?FinanceAccount;
    public function createAccount(CreateAccountDTO $dto): FinanceAccount;
    public function updateAccount(FinanceAccount $account, array $data): FinanceAccount;
    public function deleteAccount(FinanceAccount $account): bool;
    public function updateAccountBalance(int $accountId, float $amount): bool;

    // Employee Accounts
    public function createEmployeeAccount(array $data): \App\Models\EmployeeAccount;
    public function getEmployeeAccountById(int $id, int $companyId): ?\App\Models\EmployeeAccount;
    public function getAllEmployeeAccounts(int $companyId, ?int $perPage = null, ?string $search = null, ?int $page = null, array $filters = []): mixed;
    public function updateEmployeeAccount(\App\Models\EmployeeAccount $account, array $data): \App\Models\EmployeeAccount;
    public function deleteEmployeeAccount(\App\Models\EmployeeAccount $account): bool;

    // Categories (from ci_erp_constants)
    public function getAllCategories(int $companyId, ?string $type = null, ?int $perPage = null, ?string $search = null, ?int $page = null, array $filters = []): mixed;
    public function getPaymentMethods(?int $perPage = null, ?string $search = null, ?int $page = null): mixed;
    public function createCategory(CreateCategoryDTO $dto): ErpConstant;
    public function updateCategory(ErpConstant $category, array $data): ErpConstant;
    public function deleteCategory(ErpConstant $category): bool;

    // Transactions
    public function getTransactions(int $companyId, array $filters = [], ?int $perPage = null, ?string $search = null, ?int $page = null): mixed;
    public function createTransaction(CreateTransactionDTO $dto): FinanceTransaction;
    public function getTransactionById(int $id, int $companyId): ?FinanceTransaction;
    public function updateTransaction(FinanceTransaction $transaction, array $data): FinanceTransaction;
    public function deleteTransaction(FinanceTransaction $transaction): bool;

    // Reports/Balance
    public function getBalance(int $accountId): float;
}
