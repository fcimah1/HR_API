<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Payslip;
use App\Models\PayslipAllowance;
use App\Models\PayslipDeduction;
use App\Repository\Interface\ReportRepositoryInterface;
use App\Repository\Interface\PayslipRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PayslipRepository implements PayslipRepositoryInterface
{
    public function __construct(
        private readonly ReportRepositoryInterface $reportRepository,
    ) {}

    public function paginateForCompany(int $companyId, array $filters = [], bool $historyOnly = false): LengthAwarePaginator
    {
        $query = Payslip::query()
            ->with(['employee.user_details.branch', 'allowances', 'deductions'])
            ->where('company_id', $companyId);

        if (!empty($filters['salary_month'])) {
            $query->where('salary_month', $filters['salary_month']);
        }

        if (!empty($filters['staff_id'])) {
            $query->where('staff_id', (int) $filters['staff_id']);
        }

        if (!empty($filters['staff_ids'])) {
            $query->whereIn('staff_id', $filters['staff_ids']);
        }

        if (!empty($filters['salary_payment_method'])) {
            $query->where('salary_payment_method', $filters['salary_payment_method']);
        }

        if (!empty($filters['branch_id'])) {
            $branchId = (int) $filters['branch_id'];
            $query->whereHas('employee.user_details', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        if (!empty($filters['job_type'])) {
            $jobType = (int) $filters['job_type'];
            $query->whereHas('employee.user_details', function ($q) use ($jobType) {
                $q->where('job_type', $jobType);
            });
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (array_key_exists('is_payment', $filters) && $filters['is_payment'] !== null && $filters['is_payment'] !== '') {
            $query->where('is_payment', (int) $filters['is_payment']);
        }

        if ($historyOnly) {
            $query->orderBy('salary_month', 'desc');
        } else {
            $query->orderBy('payslip_id', 'desc');
        }

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 10;
        return $query->paginate($perPage);
    }

    public function findByIdForCompany(int $companyId, int $id): ?Payslip
    {
        return Payslip::query()
            ->with(['employee.user_details.branch', 'allowances', 'deductions'])
            ->where('company_id', $companyId)
            ->where('payslip_id', $id)
            ->first();
    }

    public function createDraftForCompanyMonth(int $companyId, string $salaryMonth, array $filters = []): int
    {
        $reportFilters = [
            'payment_date' => $salaryMonth,
        ];

        if (!empty($filters['staff_id'])) {
            $reportFilters['employee_id'] = (int) $filters['staff_id'];
        }

        if (!empty($filters['staff_ids'])) {
            $reportFilters['employee_ids'] = array_map('intval', (array) $filters['staff_ids']);
        }

        if (!empty($filters['branch_id'])) {
            $reportFilters['branch_id'] = (int) $filters['branch_id'];
        }

        if (!empty($filters['job_type'])) {
            $jobType = (int) $filters['job_type'];
            $jobTypeMap = [
                0 => 'part_time',
                1 => 'permanent',
                2 => 'contract',
                3 => 'probation',
            ];
            $reportFilters['job_type'] = $jobTypeMap[$jobType] ?? 'all';
        }

        if (!empty($filters['salary_payment_method'])) {
            $method = strtolower((string) $filters['salary_payment_method']);
            $reportFilters['payment_method'] = match ($method) {
                'cash' => 'cash',
                'deposit', 'bank' => 'bank',
                default => 'all',
            };
        }

        $rows = $this->reportRepository->getPayrollReport($companyId, $reportFilters);

        $created = 0;

        foreach ($rows as $row) {
            $details = $row->details;
            $payslip = Payslip::query()->create([
                'payslip_key' => bin2hex(random_bytes(32)),
                'company_id' => $companyId,
                'staff_id' => (int) $row->user_id,
                'contract_option_id' => null,
                'salary_month' => $salaryMonth,
                'wages_type' => 1,
                'payslip_type' => 'full_monthly',
                'basic_salary' => (float) $row->basic_salary,
                'daily_wages' => 0,
                'hours_worked' => 0,
                'total_allowances' => (float) $row->allowances_total,
                'total_commissions' => 0,
                'total_statutory_deductions' => (float) ($row->deductions_total - (float) $row->loan_amount - (float) $row->unpaid_leave_deduction),
                'total_other_payments' => 0,
                'net_salary' => (float) $row->net_salary,
                'payment_method' => 1,
                'pay_comments' => 1,
                'is_payment' => 0,
                'year_to_date' => date('d-m-Y'),
                'is_advance_salary_deduct' => 0,
                'advance_salary_amount' => 0,
                'is_loan_deduct' => 0,
                'loan_amount' => (float) $row->loan_amount,
                'unpaid_leave_days' => (float) $row->unpaid_leave_days,
                'unpaid_leave_deduction' => (float) $row->unpaid_leave_deduction,
                'status' => 0,
                'created_at' => date('Y-m-d'),
                'salary_payment_method' => $details?->salary_payment_method ? strtoupper((string) $details->salary_payment_method) : null,
            ]);

            foreach (($row->allowances ?? []) as $allowance) {
                PayslipAllowance::query()->create([
                    'payslip_id' => $payslip->payslip_id,
                    'staff_id' => (int) $row->user_id,
                    'is_taxable' => (int) ($allowance->is_taxable ?? 0),
                    'is_fixed' => (int) ($allowance->is_fixed ?? 1),
                    'pay_title' => (string) ($allowance->pay_title ?? ''),
                    'pay_amount' => (float) ($allowance->pay_amount ?? 0),
                    'salary_month' => $salaryMonth,
                    'created_at' => date('d-m-Y H:i:s'),
                    'contract_option_id' => $allowance->contract_option_id ?? null,
                ]);
            }

            foreach (($row->deductions ?? []) as $deduction) {
                PayslipDeduction::query()->create([
                    'payslip_id' => $payslip->payslip_id,
                    'staff_id' => (int) $row->user_id,
                    'is_fixed' => (int) ($deduction->is_fixed ?? 1),
                    'pay_title' => (string) ($deduction->pay_title ?? ''),
                    'pay_amount' => (float) ($deduction->pay_amount ?? 0),
                    'salary_month' => $salaryMonth,
                    'created_at' => date('d-m-Y H:i:s'),
                    'contract_option_id' => $deduction->contract_option_id ?? null,
                ]);
            }

            $created++;
        }

        return $created;
    }

    public function deleteDraftForCompanyMonth(int $companyId, string $salaryMonth, array $filters = []): int
    {
        $query = Payslip::query()
            ->where('company_id', $companyId)
            ->where('salary_month', $salaryMonth)
            ->where('status', 0);

        $this->applyBulkFilters($query, $filters);

        $ids = $query->pluck('payslip_id')->toArray();
        if (empty($ids)) {
            return 0;
        }

        return DB::transaction(function () use ($ids) {
            PayslipAllowance::query()->whereIn('payslip_id', $ids)->delete();
            PayslipDeduction::query()->whereIn('payslip_id', $ids)->delete();
            return Payslip::query()->whereIn('payslip_id', $ids)->delete();
        });
    }

    public function updateStatusForCompanyMonth(int $companyId, string $salaryMonth, int $status, array $filters = []): int
    {
        $query = Payslip::query()
            ->where('company_id', $companyId)
            ->where('salary_month', $salaryMonth);

        $this->applyBulkFilters($query, $filters);

        if ($status === 1) {
            $query->where('status', 0);
        } else {
            $query->where('status', 1);
        }

        return $query->update(['status' => $status]);
    }

    private function applyBulkFilters($query, array $filters): void
    {
        if (!empty($filters['staff_id'])) {
            $query->where('staff_id', (int) $filters['staff_id']);
        }

        if (!empty($filters['staff_ids'])) {
            $query->whereIn('staff_id', $filters['staff_ids']);
        }

        if (!empty($filters['salary_payment_method'])) {
            $query->where('salary_payment_method', $filters['salary_payment_method']);
        }

        if (!empty($filters['branch_id'])) {
            $branchId = (int) $filters['branch_id'];
            $query->whereHas('employee.user_details', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        if (!empty($filters['job_type'])) {
            $jobType = (int) $filters['job_type'];
            $query->whereHas('employee.user_details', function ($q) use ($jobType) {
                $q->where('job_type', $jobType);
            });
        }
    }
}
