<?php

namespace App\DTOs\Transfer;

class ExecuteTransferDTO
{
    public function __construct(
        public readonly int $executedBy,
        public readonly bool $forceCustodyClearance = false, // للطوارئ - تجاوز فحص العهد
        public readonly ?string $notes = null,
    ) {}

    /**
     * Create DTO from request data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            executedBy: $data['executed_by'],
            forceCustodyClearance: $data['force_custody_clearance'] ?? false,
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'executed_by' => $this->executedBy,
            'force_custody_clearance' => $this->forceCustodyClearance,
            'notes' => $this->notes,
        ];
    }
}
