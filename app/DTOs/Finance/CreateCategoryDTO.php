<?php

namespace App\DTOs\Finance;

use Illuminate\Http\Request;

class CreateCategoryDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $name,
        public readonly string $type // income, expense
    ) {}

    public static function fromRequest(Request $request, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            name: $request->input('name'),
            type: $request->input('type')
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'name' => $this->name,
            'type' => $this->type,
        ];
    }
}
