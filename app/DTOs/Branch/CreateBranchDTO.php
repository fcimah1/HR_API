<?php

declare(strict_types=1);

namespace App\DTOs\Branch;

class CreateBranchDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $branchName,
        public readonly string $coordinates,
        public readonly int $addedBy,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $userId): self
    {
        return new self(
            companyId: $companyId,
            branchName: $data['branch_name'],
            coordinates: $data['coordinates'],
            addedBy: $userId,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'branch_name' => $this->branchName,
            'coordinates' => $this->coordinates,
        ];
    }
}
