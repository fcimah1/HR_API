<?php

namespace App\DTOs\AdvanceSalary;

use App\Enums\TravelStatusEnum;

class CreateAdvanceSalaryDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $salaryType,
        public readonly string $monthYear,
        public readonly float $advanceAmount,
        public readonly string $oneTimeDeduct,
        public readonly float $monthlyInstallment,
        public readonly string $reason
    ) {}

    public static function fromRequest(array $data, int $companyId, int $employeeId): self
    {
        return new self(
            companyId: $companyId,
            employeeId: $employeeId,
            salaryType: $data['salary_type'],
            monthYear: $data['month_year'],
            advanceAmount: (float) $data['advance_amount'],
            oneTimeDeduct: $data['one_time_deduct'],
            monthlyInstallment: (float) $data['monthly_installment'],
            reason: $data['reason']
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'salary_type' => $this->salaryType,
            'month_year' => $this->monthYear,
            'advance_amount' => $this->advanceAmount,
            'one_time_deduct' => $this->oneTimeDeduct,
            'monthly_installment' => $this->monthlyInstallment,
            'total_paid' => 0.00,
            'reason' => $this->reason,
            'status' => TravelStatusEnum::PENDING->value, // Pending by default
            'is_deducted_from_salary' => 0,
            'created_at' => now()->format('d-m-Y h:i:s'),
        ];
    }

    public function isLoan(): bool
    {
        return $this->salaryType === 'loan';
    }

    public function isAdvance(): bool
    {
        return $this->salaryType === 'advance';
    }

    public function isOneTimeDeduct(): bool
    {
        return $this->oneTimeDeduct === '1';
    }
}

