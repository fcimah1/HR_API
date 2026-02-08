<?php

namespace App\DTOs\Branch;

use Illuminate\Http\Request;

class BranchFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?int $company_id = null,
        public ?int $branch_id = null,
        public ?bool $paginate = true,
        public ?int $per_page = 10,
        public ?int $page = 1,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->input('search'),
            company_id: $request->input('company_id'),
            branch_id: $request->input('branch_id'),
            paginate: filter_var($request->input('paginate', true), FILTER_VALIDATE_BOOLEAN),
            per_page: (int) $request->input('per_page', 10),
            page: (int) $request->input('page', 1),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'paginate' => $this->paginate,
            'per_page' => $this->per_page,
            'page' => $this->page,
        ], fn($value) => !is_null($value));
    }
}
