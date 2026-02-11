<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

class TaxTypeDTO
{
    public function __construct(
        public int $company_id,
        public string $tax_name,
        public string $tax_rate,
        public string $tax_type, // percentage or fixed
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            company_id: $companyId,
            tax_name: $data['tax_name'],
            tax_rate: (string)$data['tax_rate'],
            tax_type: $data['tax_type'] ?? 'percentage',
        );
    }
}
