<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Models\Payslip;
use Illuminate\Pagination\LengthAwarePaginator;

interface PayslipRepositoryInterface
{
    public function paginateForCompany(int $companyId, array $filters = [], bool $historyOnly = false): LengthAwarePaginator;
    public function findByIdForCompany(int $companyId, int $id): ?Payslip;
    public function createDraftForCompanyMonth(int $companyId, string $salaryMonth, array $filters = []): int;
    public function deleteDraftForCompanyMonth(int $companyId, string $salaryMonth, array $filters = []): int;
    public function updateStatusForCompanyMonth(int $companyId, string $salaryMonth, int $status, array $filters = []): int;
}
