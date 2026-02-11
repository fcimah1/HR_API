<?php

namespace App\Services;

use App\Repository\Interface\SupplierRepositoryInterface;
use App\Models\Supplier;
use App\DTOs\Supplier\SupplierFilterDTO;
use App\DTOs\Supplier\CreateSupplierDTO;
use App\DTOs\Supplier\UpdateSupplierDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SupplierService
{
    public function __construct(
        protected SupplierRepositoryInterface $supplierRepository
    ) {}

    public function getSuppliers(SupplierFilterDTO $filters): LengthAwarePaginator|Collection
    {
        return $this->supplierRepository->getAllSuppliers($filters);
    }

    public function getSupplier(int $id, int $companyId): ?Supplier
    {
        return $this->supplierRepository->getSupplierById($id, $companyId);
    }

    public function createSupplier(CreateSupplierDTO $dto): Supplier
    {
        return $this->supplierRepository->create($dto->toArray());
    }

    public function updateSupplier(int $id, int $companyId, UpdateSupplierDTO $dto): ?Supplier
    {
        $supplier = $this->getSupplier($id, $companyId);
        if (!$supplier) {
            return null;
        }
        return $this->supplierRepository->update($supplier, $dto->toArray());
    }

    public function deleteSupplier(int $id, int $companyId): bool
    {
        return $this->supplierRepository->delete($id, $companyId);
    }
}
