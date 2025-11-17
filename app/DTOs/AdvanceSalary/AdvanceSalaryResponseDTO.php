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
        public readonly string $createdAt
    ) {}

    public static function fromModel(AdvanceSalary $advance): self
    {
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
            createdAt: $advance->created_at
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
        ];
    }
}

