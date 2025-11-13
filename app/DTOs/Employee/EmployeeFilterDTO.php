<?php

namespace App\DTOs\Employee;

class EmployeeFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $page = 1,
        public readonly ?int $perPage = 10,
        public readonly ?string $sortBy = 'first_name',
        public readonly ?string $sortDirection = 'asc',
        public readonly ?string $userType = null,
        public readonly ?bool $isActive = null,
        public readonly ?int $companyId = null
    ) {}

    public static function fromRequest(array $data): self
    {
        $sortDirection = $data['sort_direction'] ?? 'asc';
        $validSortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'asc';
        
        return new self(
            search: $data['search'] ?? null,
            page: (int) ($data['page'] ?? 1),
            perPage: min((int) ($data['per_page'] ?? 10), 100),
            sortBy: $data['sort_by'] ?? 'first_name',
            sortDirection: $validSortDirection,
            userType: $data['user_type'] ?? null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null
        );
    }

    public function hasSearchFilter(): bool
    {
        return !empty($this->search);
    }

    public function hasUserTypeFilter(): bool
    {
        return !empty($this->userType);
    }

    public function hasActiveFilter(): bool
    {
        return $this->isActive !== null;
    }

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'user_type' => $this->userType,
            'is_active' => $this->isActive,
            'company_id' => $this->companyId,
        ];
    }
}
