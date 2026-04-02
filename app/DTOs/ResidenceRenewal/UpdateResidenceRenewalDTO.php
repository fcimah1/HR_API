<?php

namespace App\DTOs\ResidenceRenewal;

class UpdateResidenceRenewalDTO
{
    public function __construct(
        public readonly ?float $workPermitFee = null,
        public readonly ?float $residenceRenewalFees = null,
        public readonly ?float $penaltyAmount = null,
        public readonly ?string $currentResidenceExpiryDate = null,
        public readonly ?string $notes = null,
        public readonly ?string $status = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            workPermitFee: isset($data['work_permit_fee']) ? (float) $data['work_permit_fee'] : null,
            residenceRenewalFees: isset($data['residence_renewal_fees']) ? (float) $data['residence_renewal_fees'] : null,
            penaltyAmount: isset($data['penalty_amount']) ? (float) $data['penalty_amount'] : null,
            currentResidenceExpiryDate: $data['current_residence_expiry_date'] ?? null,
            notes: $data['notes'] ?? null,
            status: $data['status'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'work_permit_fee' => $this->workPermitFee,
            'residence_renewal_fees' => $this->residenceRenewalFees,
            'penalty_amount' => $this->penaltyAmount,
            'current_residence_expiry_date' => $this->currentResidenceExpiryDate,
            'notes' => $this->notes,
            'status' => $this->status,
        ], fn($value) => !is_null($value));
    }
}
