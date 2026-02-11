<?php

namespace App\Repository\Interface;

use App\Models\Warehouse;
use App\DTOs\Warehouse\WarehouseFilterDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface WarehouseRepositoryInterface
{
    public function getAllWarehouses(WarehouseFilterDTO $filters): LengthAwarePaginator|Collection;

    public function getWarehouseById(int $id, int $companyId): ?Warehouse;

    public function create(array $data): Warehouse;

    public function update(Warehouse $warehouse, array $data): Warehouse;

    public function delete(int $id, int $companyId): bool;
}
