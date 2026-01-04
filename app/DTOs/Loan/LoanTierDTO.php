<?php

namespace App\DTOs\Loan;

use App\Models\LoanPolicyTier;

class LoanTierDTO
{
    public function __construct(
        public readonly int $tierId,
        public readonly string $label,
        public readonly string $labelEn,
        public readonly float $salaryMultiplier,
        public readonly int $maxMonths,
        public readonly bool $isOneTime,
        public readonly ?int $minMonthsForSalary = null,
    ) {
    }

    public static function fromModel(LoanPolicyTier $tier, ?float $salary = null): self
    {
        return new self(
            tierId: $tier->tier_id,
            label: $tier->tier_label_ar,
            labelEn: $tier->tier_name . ' / ' . ($tier->max_months == 1 ? '1 month' : $tier->max_months . ' months'),
            salaryMultiplier: (float) $tier->salary_multiplier,
            maxMonths: $tier->max_months,
            isOneTime: $tier->is_one_time,
            minMonthsForSalary: $salary ? $tier->calculateMinMonths($salary) : null,
        );
    }

    public function toArray(): array
    {
        $data = [
            'tier_id' => $this->tierId,
            'label' => $this->label,
            'label_en' => $this->labelEn,
            'salary_multiplier' => $this->salaryMultiplier,
            'max_months' => $this->maxMonths,
            'is_one_time' => $this->isOneTime,
        ];

        if ($this->minMonthsForSalary !== null) {
            $data['min_months_for_salary'] = $this->minMonthsForSalary;
        }

        return $data;
    }
}
