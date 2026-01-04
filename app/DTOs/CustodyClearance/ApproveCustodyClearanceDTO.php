<?php

namespace App\DTOs\CustodyClearance;

class ApproveCustodyClearanceDTO
{
    public function __construct(
        public readonly string $action, // approve/reject
        public readonly int $processedBy,
        public readonly ?string $remarks = null,
    ) {}

    public static function fromRequest(array $data, int $processedBy): self
    {
        return new self(
            action: $data['action'],
            processedBy: $processedBy,
            remarks: $data['remarks'] ?? null,
        );
    }
}
