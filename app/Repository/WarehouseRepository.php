<?php

namespace App\Repository;

use App\Models\Warehouse;
use App\Repository\Interface\WarehouseRepositoryInterface;
use App\DTOs\Warehouse\WarehouseFilterDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WarehouseRepository implements WarehouseRepositoryInterface
{
    public function getAllWarehouses(WarehouseFilterDTO $filters): LengthAwarePaginator|Collection
    {
        $query = Warehouse::forCompany($filters->companyId);

        if ($filters->warehouseName) {
            $query->where('warehouse_name', 'like', '%' . $filters->warehouseName . '%');
        }

        if ($filters->city) {
            $query->where('city', 'like', '%' . $filters->city . '%');
        }

        if ($filters->country) {
            $query->where('country', $filters->country);
        }

        if ($filters->paginate) {
            return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
        }

        return $query->get();
    }

    public function getWarehouseById(int $id, int $companyId): ?Warehouse
    {
        return Warehouse::forCompany($companyId)->find($id);
    }

    public function create(array $data): Warehouse
    {
        return Warehouse::create($data);
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update($data);
        return $warehouse;
    }

    public function delete(int $id, int $companyId): bool
    {
        $warehouse = $this->getWarehouseById($id, $companyId);
        if (!$warehouse) {
            return false;
        }
        return $warehouse->delete();
    }
}
