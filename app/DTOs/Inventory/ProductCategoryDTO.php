<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

class ProductCategoryDTO
{
    public function __construct(
        public int $company_id,
        public string $category_name,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            company_id: $companyId,
            category_name: $data['category_name'],
        );
    }
}
