<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payslip;
use App\Repository\Interface\PayslipRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class PayslipService
{
    public function __construct(
        private readonly PayslipRepositoryInterface $payslipRepository,
        private readonly SimplePermissionService $permissionService,
    ) {}

    public function getPaginatedPayslips(int $companyId, User $user, array $filters): LengthAwarePaginator
    {
        $this->applyStaffVisibilityFilters($filters, $companyId, $user);
        return $this->payslipRepository->paginateForCompany($companyId, $filters);
    }

    public function getPayslipHistory(int $companyId, User $user, array $filters): LengthAwarePaginator
    {
        $this->applyStaffVisibilityFilters($filters, $companyId, $user);
        return $this->payslipRepository->paginateForCompany($companyId, $filters, true);
    }

    public function getPayslipApproveList(int $companyId, User $user, array $filters): LengthAwarePaginator
    {
        $this->applyStaffVisibilityFilters($filters, $companyId, $user);

        $filters['status'] = 0;
        return $this->payslipRepository->paginateForCompany($companyId, $filters);
    }

    public function getPayslipUnpaidList(int $companyId, User $user, array $filters): LengthAwarePaginator
    {
        $this->applyStaffVisibilityFilters($filters, $companyId, $user);

        $filters['status'] = 0;
        return $this->payslipRepository->paginateForCompany($companyId, $filters);
    }

    public function getPayslipDraftList(int $companyId, User $user, array $filters): LengthAwarePaginator
    {
        $this->applyStaffVisibilityFilters($filters, $companyId, $user);

        $filters['status'] = 0;
        $filters['is_payment'] = 0;
        return $this->payslipRepository->paginateForCompany($companyId, $filters);
    }

    public function getPayslipByIdForCompany(int $companyId, User $user, int $id): ?Payslip
    {
        $payslip = $this->payslipRepository->findByIdForCompany($companyId, $id);
        if (!$payslip) {
            return null;
        }

        if ($user->user_type !== 'company' && !$this->permissionService->isCompanyOwner($user)) {
            $allowedEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId, true);
            $allowedIds = array_column($allowedEmployees, 'user_id');
            if (!in_array($payslip->staff_id, $allowedIds)) {
                return null;
            }
        }

        return $payslip;
    }

    public function createDraftPayslips(int $companyId, User $user, array $filters): int
    {
        $this->applyStaffVisibilityFilters($filters, $companyId, $user);

        $salaryMonth = $filters['salary_month'] ?? null;
        if (!$salaryMonth) {
            throw new \InvalidArgumentException('الشهر مطلوب');
        }

        return DB::transaction(function () use ($companyId, $filters, $salaryMonth) {
            $this->payslipRepository->deleteDraftForCompanyMonth($companyId, $salaryMonth, $filters);
            return $this->payslipRepository->createDraftForCompanyMonth($companyId, $salaryMonth, $filters);
        });
    }

    public function cancelDraftPayslips(int $companyId, User $user, array $filters): int
    {
        $this->applyStaffVisibilityFilters($filters, $companyId, $user);

        $salaryMonth = $filters['salary_month'] ?? null;
        if (!$salaryMonth) {
            throw new \InvalidArgumentException('الشهر مطلوب');
        }

        return $this->payslipRepository->deleteDraftForCompanyMonth($companyId, $salaryMonth, $filters);
    }

    public function approveDraftPayslips(int $companyId, User $user, array $filters): int
    {
        $this->applyStaffVisibilityFilters($filters, $companyId, $user);

        $salaryMonth = $filters['salary_month'] ?? null;
        if (!$salaryMonth) {
            throw new \InvalidArgumentException('الشهر مطلوب');
        }

        return $this->payslipRepository->updateStatusForCompanyMonth($companyId, $salaryMonth, 1, $filters);
    }

    public function cancelApprovePayslips(int $companyId, User $user, array $filters): int
    {
        $this->applyStaffVisibilityFilters($filters, $companyId, $user);

        $salaryMonth = $filters['salary_month'] ?? null;
        if (!$salaryMonth) {
            throw new \InvalidArgumentException('الشهر مطلوب');
        }

        return $this->payslipRepository->updateStatusForCompanyMonth($companyId, $salaryMonth, 0, $filters);
    }

    private function applyStaffVisibilityFilters(array &$filters, int $companyId, User $user): void
    {
        if ($user->user_type === 'company' || $this->permissionService->isCompanyOwner($user)) {
            return;
        }

        $allowedEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId, true);
        $allowedIds = array_column($allowedEmployees, 'user_id');

        if (!empty($filters['staff_id'])) {
            if (!in_array((int) $filters['staff_id'], $allowedIds)) {
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
            }
            return;
        }

        $filters['staff_ids'] = $allowedIds;
    }
}
