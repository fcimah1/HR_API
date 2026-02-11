<?php

namespace App\Repository\Interface;

use App\Models\Supplier;
use App\DTOs\Supplier\SupplierFilterDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SupplierRepositoryInterface
{
    public function getAllSuppliers(SupplierFilterDTO $filters): LengthAwarePaginator|Collection;

    public function getSupplierById(int $id, int $companyId): ?Supplier;

    public function create(array $data): Supplier;

    public function update(Supplier $supplier, array $data): Supplier;

    public function delete(int $id, int $companyId): bool;
}
