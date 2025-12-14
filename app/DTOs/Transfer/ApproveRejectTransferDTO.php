<?php

namespace App\DTOs\Transfer;

use App\Models\Transfer;
use Spatie\LaravelData\Data;

class ApproveRejectTransferDTO extends Data
{
    public function __construct(
        public readonly string $action,  // 'approve' or 'reject'
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

    public function getNewStatus(): int
    {
        return match ($this->action) {
            'approve' => Transfer::STATUS_APPROVED,
            'reject' => Transfer::STATUS_REJECTED,
            default => Transfer::STATUS_PENDING,
        };
    }
}
