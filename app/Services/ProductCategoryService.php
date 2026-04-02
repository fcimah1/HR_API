<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\ProductCategoryRepository;
use App\Models\ErpConstant;
use App\DTOs\Inventory\ProductCategoryDTO;
use App\DTOs\Inventory\ProductCategoryFilterDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ProductCategoryService
{
    public function __construct(
        private readonly ProductCategoryRepository $categoryRepository
    ) {}

    public function getCategories(ProductCategoryFilterDTO $filters): LengthAwarePaginator|Collection
    {
        Log::info('ProductCategoryService::getCategories called', ['filters' => $filters]);
        return $this->categoryRepository->getAll($filters);
    }

    public function getCategoryById(int $id, int $companyId): ?ErpConstant
    {
        Log::info('ProductCategoryService::getCategoryById called', ['id' => $id, 'company_id' => $companyId]);
        return $this->categoryRepository->findById($id, $companyId);
    }

    public function createCategory(ProductCategoryDTO $dto): ErpConstant
    {
        Log::info('ProductCategoryService::createCategory called', ['category_name' => $dto->category_name]);
        return $this->categoryRepository->create([
            'company_id' => $dto->company_id,
            'type' => 'product_category',
            'category_name' => $dto->category_name,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function updateCategory(int $id, int $companyId, ProductCategoryDTO $dto): ?ErpConstant
    {
        Log::info('ProductCategoryService::updateCategory called', ['id' => $id]);
        $category = $this->categoryRepository->findById($id, $companyId);
        if (!$category) {
            return null;
        }

        $this->categoryRepository->update($category, [
            'category_name' => $dto->category_name,
        ]);

        return $category->fresh();
    }

    public function deleteCategory(int $id, int $companyId): bool
    {
        Log::info('ProductCategoryService::deleteCategory called', ['id' => $id]);
        $category = $this->categoryRepository->findById($id, $companyId);
        if (!$category) {
            return false;
        }

        return $this->categoryRepository->delete($category);
    }
}
