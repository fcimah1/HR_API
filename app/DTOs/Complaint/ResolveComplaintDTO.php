<?php

namespace App\DTOs\Complaint;

use App\Enums\NumericalStatusEnum;
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
            'resolve' => NumericalStatusEnum::APPROVED->value,
            'reject' => NumericalStatusEnum::REJECTED->value,
            default => NumericalStatusEnum::PENDING->value,
        };
    }
}
