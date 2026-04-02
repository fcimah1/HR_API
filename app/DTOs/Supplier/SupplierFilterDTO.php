<?php

declare(strict_types=1);

namespace App\DTOs\Supplier;

class SupplierFilterDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?string $supplierName = null,
        public readonly ?string $email = null,
        public readonly ?string $city = null,
        public readonly bool $paginate = true,
        public readonly int $perPage = 10,
        public readonly int $page = 1,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            supplierName: $data['supplier_name'] ?? null,
            email: $data['email'] ?? null,
            city: $data['city'] ?? null,
            paginate: filter_var($data['paginate'] ?? true, FILTER_VALIDATE_BOOLEAN),
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 10,
            page: isset($data['page']) ? (int) $data['page'] : 1,
        );
    }
}
