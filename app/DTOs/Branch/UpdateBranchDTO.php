<?php

declare(strict_types=1);

namespace App\DTOs\Branch;

class UpdateBranchDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly string $branchName,
        public readonly string $coordinates,
        public readonly int $updatedBy,
    ) {}

    public static function fromRequest(array $data, int $id, int $companyId, int $userId): self
    {
        return new self(
            id: $id,
            companyId: $companyId,
            branchName: $data['branch_name'],
            coordinates: $data['coordinates'],
            updatedBy: $userId,
        );
    }

    public function toArray(): array
    {
        return [
            'branch_name' => $this->branchName,
            'coordinates' => $this->coordinates,
        ];
    }
}
