<?php

namespace App\DTOs\Loan;

class LoanEligibilityDTO
{
    public function __construct(
        public readonly bool $eligible,
        public readonly ?array $employee = null,
        public readonly ?array $eligibilityChecks = null,
        public readonly array $blockedReasons = [],
    ) {
    }

    public static function eligible(array $employee, array $checks): self
    {
        return new self(
            eligible: true,
            employee: $employee,
            eligibilityChecks: $checks,
            blockedReasons: [],
        );
    }

    public static function notEligible(array $reasons, ?array $employee = null): self
    {
        return new self(
            eligible: false,
            employee: $employee,
            eligibilityChecks: null,
            blockedReasons: $reasons,
        );
    }

    public function toArray(): array
    {
        $data = [
            'eligible' => $this->eligible,
        ];

        if ($this->employee !== null) {
            $data['employee'] = $this->employee;
        }

        if ($this->eligibilityChecks !== null) {
            $data['eligibility_checks'] = $this->eligibilityChecks;
        }

        if (!empty($this->blockedReasons)) {
            $data['blocked_reasons'] = $this->blockedReasons;
        }

        return $data;
    }
}
