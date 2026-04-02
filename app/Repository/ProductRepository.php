<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Product;
use App\DTOs\Product\ProductFilterDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    public function getAllProducts(ProductFilterDTO $filters): LengthAwarePaginator|Collection
    {
        $query = Product::forCompany($filters->company_id)
            ->with(['warehouse', 'category']);

        if ($filters->product_name) {
            $query->where('product_name', 'like', '%' . $filters->product_name . '%');
        }

        if ($filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('product_name', 'like', '%' . $filters->search . '%')
                    ->orWhere('barcode', 'like', '%' . $filters->search . '%')
                    ->orWhere('product_sku', 'like', '%' . $filters->search . '%')
                    ->orWhere('product_serial_number', 'like', '%' . $filters->search . '%');
            });
        }

        if ($filters->warehouse_id) {
            $query->where('warehouse_id', $filters->warehouse_id);
        }

        if ($filters->category_id) {
            $query->where('category_id', $filters->category_id);
        }

        // Handle nullable booleans
        if ($filters->out_of_stock !== null) {
            if ($filters->out_of_stock) {
                $query->where('product_qty', '<', 1);
            } else {
                $query->where('product_qty', '>=', 1);
            }
        }

        if ($filters->expired !== null) {
            if ($filters->expired) {
                $query->where('expiration_date', '<', now()->format('Y-m-d'));
            } else {
                $query->where(function ($q) {
                    $q->where('expiration_date', '>=', now()->format('Y-m-d'))
                        ->orWhereNull('expiration_date');
                });
            }
        }

        $query->orderBy('product_id', 'desc');

        if ($filters->paginate) {
            return $query->paginate($filters->per_page ?? 10);
        }

        return $query->get();
    }

    public function findById(int $id, int $companyId): ?Product
    {
        return Product::forCompany($companyId)
            ->with(['warehouse', 'category'])
            ->find($id);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): bool
    {
        return (bool) $product->update($data);
    }

    public function delete(Product $product): bool
    {
        return (bool) $product->delete();
    }
}
