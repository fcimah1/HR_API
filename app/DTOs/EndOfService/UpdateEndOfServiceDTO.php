<?php

declare(strict_types=1);

namespace App\DTOs\EndOfService;

class UpdateEndOfServiceDTO
{
    public function __construct(
        public readonly ?string $notes = null,
        public readonly ?bool $isApproved = null,
        public readonly ?int $approvedBy = null,
    ) {}

    public static function fromRequest(array $data, ?int $approvedBy = null): self
    {
        $isApproved = isset($data['is_approved']) ? filter_var($data['is_approved'], FILTER_VALIDATE_BOOLEAN) : null;

        return new self(
            notes: $data['notes'] ?? null,
            isApproved: $isApproved,
            approvedBy: $isApproved ? $approvedBy : null,
        );
    }
}
