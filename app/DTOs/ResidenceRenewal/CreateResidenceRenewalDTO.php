<?php

namespace App\DTOs\ResidenceRenewal;

class CreateResidenceRenewalDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly float $workPermitFee,
        public readonly float $residenceRenewalFees,
        public readonly float $penaltyAmount,
        public readonly string $currentResidenceExpiryDate,
        public readonly bool $isManualShares = false,
        public readonly ?float $employeeShare = null,
        public readonly ?float $companyShare = null,
        public readonly ?string $notes = null,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $userId): self
    {
        return new self(
            companyId: $companyId,
            employeeId: (int) $data['employee_id'],
            workPermitFee: (float) $data['work_permit_fee'],
            residenceRenewalFees: (float) $data['residence_renewal_fees'],
            penaltyAmount: (float) $data['penalty_amount'],
            currentResidenceExpiryDate: $data['current_residence_expiry_date'],
            isManualShares: filter_var($data['is_manual_shares'] ?? false, FILTER_VALIDATE_BOOLEAN),
            employeeShare: isset($data['employee_share']) ? (float) $data['employee_share'] : null,
            companyShare: isset($data['company_share']) ? (float) $data['company_share'] : null,
            notes: $data['notes'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'work_permit_fee' => $this->workPermitFee,
            'residence_renewal_fees' => $this->residenceRenewalFees,
            'penalty_amount' => $this->penaltyAmount,
            'current_residence_expiry_date' => $this->currentResidenceExpiryDate,
            'employee_share' => $this->employeeShare,
            'company_share' => $this->companyShare,
            'notes' => $this->notes,
        ];
    }
}
