<?php

namespace App\DTOs\CustodyClearance;

class CreateCustodyClearanceDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $clearanceDate,
        public readonly string $clearanceType, // resignation/termination/transfer/other
        public readonly ?array $assetIds = null, // null = all custodies
        public readonly ?string $notes = null,
        public readonly int $createdBy,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $createdBy): self
    {
        return new self(
            companyId: $companyId,
            employeeId: (int) ($data['employee_id'] ?? $createdBy),
            clearanceDate: $data['clearance_date'],
            clearanceType: $data['clearance_type'] ?? 'other',
            assetIds: $data['asset_ids'] ?? null,
            notes: $data['notes'] ?? null,
            createdBy: $createdBy,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'clearance_date' => $this->clearanceDate,
            'clearance_type' => $this->clearanceType,
            'notes' => $this->notes,
            'status' => 'pending',
            'created_by' => $this->createdBy,
        ];
    }
}
