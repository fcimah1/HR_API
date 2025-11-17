<?php

namespace App\DTOs\AdvanceSalary;

class UpdateAdvanceSalaryDTO
{
    public function __construct(
        public readonly ?string $monthYear = null,
        public readonly ?float $advanceAmount = null,
        public readonly ?string $oneTimeDeduct = null,
        public readonly ?float $monthlyInstallment = null,
        public readonly ?string $reason = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            monthYear: $data['month_year'] ?? null,
            advanceAmount: isset($data['advance_amount']) ? (float) $data['advance_amount'] : null,
            oneTimeDeduct: $data['one_time_deduct'] ?? null,
            monthlyInstallment: isset($data['monthly_installment']) ? (float) $data['monthly_installment'] : null,
            reason: $data['reason'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->monthYear !== null) {
            $data['month_year'] = $this->monthYear;
        }

        if ($this->advanceAmount !== null) {
            $data['advance_amount'] = $this->advanceAmount;
        }

        if ($this->oneTimeDeduct !== null) {
            $data['one_time_deduct'] = $this->oneTimeDeduct;
        }

        if ($this->monthlyInstallment !== null) {
            $data['monthly_installment'] = $this->monthlyInstallment;
        }

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        return $data;
    }

    public function hasUpdates(): bool
    {
        return !empty($this->toArray());
    }
}

