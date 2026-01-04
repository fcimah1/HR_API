<?php

namespace App\DTOs\Loan;

use App\Models\LoanPolicyTier;

class LoanPreviewDTO
{
    public function __construct(
        public readonly int $tierId,
        public readonly string $tierLabel,
        public readonly int $maxMonths,
        public readonly float $employeeSalary,
        public readonly float $loanAmount,
        public readonly int $requestedMonths,
        public readonly float $monthlyInstallment,
        public readonly float $maxAllowedDeduction,
        public readonly int $minMonths,
        public readonly bool $isValid,
        public readonly ?string $validationMessage = null,
    ) {
    }

    public static function calculate(LoanPolicyTier $tier, float $salary, int $requestedMonths): self
    {
        $loanAmount = $tier->calculateLoanAmount($salary);
        $maxAllowedDeduction = round($salary * 0.50, 2);
        $minMonths = $tier->calculateMinMonths($salary);

        // Validate requested months
        $isValid = true;
        $validationMessage = null;

        if ($requestedMonths < 1) {
            $isValid = false;
            $validationMessage = 'عدد الأشهر يجب أن يكون 1 على الأقل';
        } elseif ($requestedMonths > $tier->max_months) {
            $isValid = false;
            $validationMessage = "عدد الأشهر يتجاوز الحد الأقصى المسموح ({$tier->max_months} شهور)";
        } else {
            $monthlyInstallment = round($loanAmount / $requestedMonths, 2);

            if ($monthlyInstallment > $maxAllowedDeduction) {
                $isValid = false;
                $validationMessage = "القسط الشهري (" . number_format($monthlyInstallment, 2) . ") يتجاوز الحد الأقصى المسموح (50% من الراتب = " . number_format($maxAllowedDeduction, 2) . ")";
            }
        }

        $monthlyInstallment = $isValid || $requestedMonths >= 1
            ? round($loanAmount / max(1, $requestedMonths), 2)
            : 0;

        return new self(
            tierId: $tier->tier_id,
            tierLabel: $tier->tier_label_ar,
            maxMonths: $tier->max_months,
            employeeSalary: $salary,
            loanAmount: $loanAmount,
            requestedMonths: $requestedMonths,
            monthlyInstallment: $monthlyInstallment,
            maxAllowedDeduction: $maxAllowedDeduction,
            minMonths: $minMonths,
            isValid: $isValid,
            validationMessage: $validationMessage,
        );
    }

    public function toArray(): array
    {
        return [
            'tier' => [
                'tier_id' => $this->tierId,
                'label' => $this->tierLabel,
                'max_months' => $this->maxMonths,
            ],
            'calculation' => [
                'employee_salary' => $this->employeeSalary,
                'loan_amount' => $this->loanAmount,
                'requested_months' => $this->requestedMonths,
                'monthly_installment' => $this->monthlyInstallment,
                'max_allowed_deduction' => $this->maxAllowedDeduction,
                'min_months' => $this->minMonths,
                'is_valid' => $this->isValid,
                'validation_message' => $this->validationMessage,
            ],
        ];
    }
}
