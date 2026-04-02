<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\ErpConstant;
use App\DTOs\Inventory\ProductCategoryFilterDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductCategoryRepository
{
    public function getAll(ProductCategoryFilterDTO $filters): LengthAwarePaginator|Collection
    {
        $query = ErpConstant::forCompany($filters->company_id)
            ->where('type', 'product_category');

        if ($filters->search) {
            $query->where('category_name', 'like', '%' . $filters->search . '%');
        }

        $query->orderBy('constants_id', 'desc');

        if ($filters->paginate) {
            return $query->paginate($filters->per_page ?? 10);
        }

        return $query->get();
    }

    public function findById(int $id, int $companyId): ?ErpConstant
    {
        return ErpConstant::forCompany($companyId)
            ->where('type', 'product_category')
            ->find($id);
    }

    public function create(array $data): ErpConstant
    {
        return ErpConstant::create($data);
    }

    public function update(ErpConstant $category, array $data): bool
    {
        return (bool) $category->update($data);
    }

    public function delete(ErpConstant $category): bool
    {
        return (bool) $category->delete();
    }
}
