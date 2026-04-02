<?php

declare(strict_types=1);

namespace App\DTOs\Product;

class ProductFilterDTO
{
    public function __construct(
        public int $company_id,
        public ?string $product_name = null,
        public ?string $search = null,
        public ?int $warehouse_id = null,
        public ?int $category_id = null,
        public ?bool $out_of_stock = null,
        public ?bool $expired = null,
        public bool $paginate = true,
        public ?int $per_page = 10,
        public ?int $page = 1,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            company_id: $companyId,
            product_name: $data['product_name'] ?? null,
            search: $data['search'] ?? null,
            warehouse_id: isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : null,
            category_id: isset($data['category_id']) ? (int)$data['category_id'] : null,
            out_of_stock: isset($data['out_of_stock']) ? filter_var($data['out_of_stock'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
            expired: isset($data['expired']) ? filter_var($data['expired'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
            paginate: filter_var($data['paginate'] ?? true, FILTER_VALIDATE_BOOLEAN),
            per_page: isset($data['per_page']) ? (int)$data['per_page'] : 10,
            page: isset($data['page']) ? (int)$data['page'] : 1,
        );
    }
}
