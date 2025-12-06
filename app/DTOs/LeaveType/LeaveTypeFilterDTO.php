<?php

namespace App\DTOs\LeaveType;

use Illuminate\Http\Request;

class LeaveTypeFilterDTO
{
    public ?string $search;
    public ?int $perPage;
    public ?int $page;
    public ?int $companyId;

    public function __construct(
        ?string $search = null,
        ?int $perPage = 15,
        ?int $page = 1,
        ?int $companyId = null
    ) {
        $this->search = $search;
        $this->perPage = $perPage;
        $this->page = $page;
        $this->companyId = $companyId;
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->input('search'),
            perPage: $request->input('per_page', 15),
            page: $request->input('page', 1),
            companyId: $request->attributes->get('effective_company_id')
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            perPage: $data['per_page'] ?? 15,
            page: $data['page'] ?? 1,
            companyId: $data['company_id'] ?? null
        );
    }

    /**
     * تحويل خصائص الكائن إلى مصفوفة
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'company_id' => $this->companyId
        ];
    }
}
