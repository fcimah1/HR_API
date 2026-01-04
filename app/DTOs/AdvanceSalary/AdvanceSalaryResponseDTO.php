<?php

namespace App\DTOs\AdvanceSalary;

use App\Models\AdvanceSalary;

class AdvanceSalaryResponseDTO
{
    public function __construct(
        public readonly int $advanceSalaryId,
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $employeeName,
        public readonly string $salaryType,
        public readonly string $salaryTypeText,
        public readonly string $monthYear,
        public readonly float $advanceAmount,
        public readonly string $oneTimeDeduct,
        public readonly string $oneTimeDeductText,
        public readonly float $monthlyInstallment,
        public readonly float $totalPaid,
        public readonly float $remainingAmount,
        public readonly string $reason,
        public readonly int $status,
        public readonly string $statusText,
        public readonly int $isDeductedFromSalary,
        public readonly string $createdAt,
        public readonly ?array $employee = null,
        public readonly ?array $approvals = null,
        public readonly ?int $loanTierId = null,
        public readonly ?string $tierLabel = null,
        public readonly ?float $employeeSalary = null,
        public readonly ?int $requestedMonths = null,
        public readonly ?int $guarantorId = null,
    ) {
    }

    public static function fromModel(AdvanceSalary $advance): self
    {
        // Load relationships if not already loaded
        if (!$advance->relationLoaded('employee')) {
            $advance->load('employee');
        }
        if (!$advance->relationLoaded('approvals')) {
            $advance->load('approvals.staff');
        }

        $employee = $advance->employee ? [
            'user_id' => $advance->employee->user_id,
            'first_name' => $advance->employee->first_name,
            'last_name' => $advance->employee->last_name,
            'email' => $advance->employee->email,
            'full_name' => $advance->employee->full_name,
        ] : null;

        $approvals = $advance->approvals->map(function ($approval) {
            return [
                'staff_approval_id' => $approval->staff_approval_id,
                'staff_id' => $approval->staff_id,
                'staff_name' => $approval->staff ? $approval->staff->full_name : null,
                'status' => $approval->status,
                'approval_level' => $approval->approval_level,
                'updated_at' => $approval->updated_at,
            ];
        })->toArray();

        // Load tier if exists
        $tierLabel = null;
        if ($advance->loan_tier_id) {
            if (!$advance->relationLoaded('tier')) {
                $advance->load('tier');
            }
            $tierLabel = $advance->tier?->tier_label_ar;
        }

        return new self(
            advanceSalaryId: $advance->advance_salary_id,
            companyId: $advance->company_id,
            employeeId: $advance->employee_id,
            employeeName: $advance->employee ?
            ($advance->employee->first_name . ' ' . $advance->employee->last_name) : 'غير محدد',
            salaryType: $advance->salary_type,
            salaryTypeText: $advance->getTypeText(),
            monthYear: $advance->month_year,
            advanceAmount: (float) $advance->advance_amount,
            oneTimeDeduct: $advance->one_time_deduct,
            oneTimeDeductText: $advance->getOneTimeDeductText(),
            monthlyInstallment: (float) $advance->monthly_installment,
            totalPaid: (float) $advance->total_paid,
            remainingAmount: $advance->getRemainingAmount(),
            reason: $advance->reason,
            status: $advance->status,
            statusText: $advance->getStatusText(),
            isDeductedFromSalary: $advance->is_deducted_from_salary,
            createdAt: $advance->created_at,
            employee: $employee,
            approvals: $approvals,
            loanTierId: $advance->loan_tier_id,
            tierLabel: $tierLabel,
            employeeSalary: $advance->employee_salary ? (float) $advance->employee_salary : null,
            requestedMonths: $advance->requested_months,
            guarantorId: $advance->guarantor_id,
        );
    }

    public function toArray(): array
    {
        return [
            'advance_salary_id' => $this->advanceSalaryId,
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'employee_name' => $this->employeeName,
            'salary_type' => $this->salaryType,
            'salary_type_text' => $this->salaryTypeText,
            'month_year' => $this->monthYear,
            'advance_amount' => $this->advanceAmount,
            'one_time_deduct' => $this->oneTimeDeduct,
            'one_time_deduct_text' => $this->oneTimeDeductText,
            'monthly_installment' => $this->monthlyInstallment,
            'total_paid' => $this->totalPaid,
            'remaining_amount' => $this->remainingAmount,
            'reason' => $this->reason,
            'status' => $this->status,
            'status_text' => $this->statusText,
            'is_deducted_from_salary' => $this->isDeductedFromSalary,
            'created_at' => $this->createdAt,
            'employee' => $this->employee,
            'approvals' => $this->approvals,
            'loan_tier_id' => $this->loanTierId,
            'tier_label' => $this->tierLabel,
            'employee_salary' => $this->employeeSalary,
            'requested_months' => $this->requestedMonths,
            'guarantor_id' => $this->guarantorId,
        ];
    }
}
