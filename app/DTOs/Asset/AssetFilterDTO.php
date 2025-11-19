<?php

namespace App\DTOs\Asset;

class AssetFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $page = 1,
        public readonly ?int $perPage = 10,
        public readonly ?string $sortBy = 'name',
        public readonly ?string $sortDirection = 'asc',
        public readonly ?int $employeeId = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $brandId = null,
        public readonly ?bool $isWorking = null,
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
            sortBy: $data['sort_by'] ?? 'name',
            sortDirection: $validSortDirection,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            brandId: isset($data['brand_id']) ? (int) $data['brand_id'] : null,
            isWorking: isset($data['is_working']) ? (bool) $data['is_working'] : null,
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null
        );
    }

    public function hasSearchFilter(): bool
    {
        return !empty($this->search);
    }

    public function hasEmployeeFilter(): bool
    {
        return $this->employeeId !== null;
    }

    public function hasCategoryFilter(): bool
    {
        return $this->categoryId !== null;
    }

    public function hasBrandFilter(): bool
    {
        return $this->brandId !== null;
    }

    public function hasStatusFilter(): bool
    {
        return $this->isWorking !== null;
    }

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'employee_id' => $this->employeeId,
            'category_id' => $this->categoryId,
            'brand_id' => $this->brandId,
            'is_working' => $this->isWorking,
            'company_id' => $this->companyId,
        ];
    }
}

