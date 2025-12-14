<?php

namespace App\DTOs\Resignation;

use App\Models\Resignation;
use Spatie\LaravelData\Data;

class ApproveRejectResignationDTO extends Data
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
            'approve' => Resignation::STATUS_APPROVED,
            'reject' => Resignation::STATUS_REJECTED,
            default => Resignation::STATUS_PENDING,
        };
    }
}
