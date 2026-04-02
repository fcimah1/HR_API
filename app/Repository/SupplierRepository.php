<?php

namespace App\Repository;

use App\Models\Supplier;
use App\Repository\Interface\SupplierRepositoryInterface;
use App\DTOs\Supplier\SupplierFilterDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SupplierRepository implements SupplierRepositoryInterface
{
    public function getAllSuppliers(SupplierFilterDTO $filters): LengthAwarePaginator|Collection
    {
        $query = Supplier::forCompany($filters->companyId);

        if ($filters->supplierName) {
            $query->where('supplier_name', 'like', '%' . $filters->supplierName . '%');
        }

        if ($filters->email) {
            $query->where('email', 'like', '%' . $filters->email . '%');
        }

        if ($filters->city) {
            $query->where('city', 'like', '%' . $filters->city . '%');
        }

        if ($filters->paginate) {
            return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
        }

        return $query->get();
    }

    public function getSupplierById(int $id, int $companyId): ?Supplier
    {
        return Supplier::forCompany($companyId)->find($id);
    }

    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier;
    }

    public function delete(int $id, int $companyId): bool
    {
        $supplier = $this->getSupplierById($id, $companyId);
        if (!$supplier) {
            return false;
        }
        return $supplier->delete();
    }
}
