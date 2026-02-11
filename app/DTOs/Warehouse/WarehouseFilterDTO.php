<?php

declare(strict_types=1);

namespace App\DTOs\Warehouse;

class WarehouseFilterDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?string $warehouseName = null,
        public readonly ?string $city = null,
        public readonly ?int $country = null,
        public readonly bool $paginate = true,
        public readonly int $perPage = 10,
        public readonly int $page = 1,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            warehouseName: $data['warehouse_name'] ?? null,
            city: $data['city'] ?? null,
            country: isset($data['country']) ? (int) $data['country'] : null,
            paginate: filter_var($data['paginate'] ?? true, FILTER_VALIDATE_BOOLEAN),
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 10,
            page: isset($data['page']) ? (int) $data['page'] : 1,
        );
    }
}
