<?php

namespace App\DTOs\Transfer;

use Spatie\LaravelData\Data;

class GetBranchesDTO extends Data
{
    public function __construct(
        public readonly int $companyId
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            companyId: (int) $data['company_id']
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
        ];
    }
}
