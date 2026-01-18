<?php

namespace App\DTOs\Branch;

use Illuminate\Http\Request;

class BranchFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?int $company_id = null,
        public ?int $branch_id = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->input('search'),
            company_id: $request->input('company_id'),
            branch_id: $request->input('branch_id'),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
        ], fn($value) => !is_null($value));
    }
}
