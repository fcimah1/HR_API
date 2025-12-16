<?php

namespace App\DTOs\Complaint;

use App\Models\Complaint;
use Spatie\LaravelData\Data;

class ResolveComplaintDTO extends Data
{
    public function __construct(
        public readonly string $action,  // 'resolve' or 'reject'
        public readonly int $processedBy,
        public readonly ?string $description = null,
    ) {}

    public static function fromRequest(array $data, int $processedBy): self
    {
        return new self(
            action: $data['action'],
            processedBy: $processedBy,
            description: $data['description'] ?? null,
        );
    }

    public function getNewStatus(): int
    {
        return match ($this->action) {
            'resolve' => Complaint::STATUS_RESOLVED,
            'reject' => Complaint::STATUS_REJECTED,
            default => Complaint::STATUS_PENDING,
        };
    }
}
