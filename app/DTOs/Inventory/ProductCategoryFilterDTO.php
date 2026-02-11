<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

class ProductCategoryFilterDTO
{
    public function __construct(
        public int $company_id,
        public ?string $search = null,
        public bool $paginate = true,
        public ?int $per_page = 10,
        public ?int $page = 1,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            company_id: $companyId,
            search: $data['search'] ?? null,
            paginate: filter_var($data['paginate'] ?? true, FILTER_VALIDATE_BOOLEAN),
            per_page: isset($data['per_page']) ? (int)$data['per_page'] : 10,
            page: isset($data['page']) ? (int)$data['page'] : 1,
        );
    }
}
