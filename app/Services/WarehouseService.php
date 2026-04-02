<?php

namespace App\Services;

use App\Repository\Interface\WarehouseRepositoryInterface;
use App\Models\Warehouse;
use App\DTOs\Warehouse\WarehouseFilterDTO;
use App\DTOs\Warehouse\CreateWarehouseDTO;
use App\DTOs\Warehouse\UpdateWarehouseDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WarehouseService
{
    public function __construct(
        protected WarehouseRepositoryInterface $warehouseRepository
    ) {}

    public function getWarehouses(WarehouseFilterDTO $filters): LengthAwarePaginator|Collection
    {
        return $this->warehouseRepository->getAllWarehouses($filters);
    }

    public function getWarehouse(int $id, int $companyId): ?Warehouse
    {
        return $this->warehouseRepository->getWarehouseById($id, $companyId);
    }

    public function createWarehouse(CreateWarehouseDTO $dto): Warehouse
    {
        return $this->warehouseRepository->create($dto->toArray());
    }

    public function updateWarehouse(int $id, int $companyId, UpdateWarehouseDTO $dto): ?Warehouse
    {
        $warehouse = $this->getWarehouse($id, $companyId);
        if (!$warehouse) {
            return null;
        }
        return $this->warehouseRepository->update($warehouse, $dto->toArray());
    }

    public function deleteWarehouse(int $id, int $companyId): bool
    {
        return $this->warehouseRepository->delete($id, $companyId);
    }
}
