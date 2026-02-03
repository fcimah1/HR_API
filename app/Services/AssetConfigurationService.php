<?php

namespace App\Services;

use App\Models\ErpConstant;
use App\Repository\AssetConfigurationRepository;
use Illuminate\Database\Eloquent\Collection;

class AssetConfigurationService
{
    protected $repository;

    public function __construct(AssetConfigurationRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get Categories for a company.
     */
    public function getCategories(int $companyId, ?string $search = null): Collection
    {
        return $this->repository->getCategories($companyId, ErpConstant::TYPE_ASSETS_CATEGORY, $search);
    }

    /**
     * Get Brands for a company.
     */
    public function getBrands(int $companyId, ?string $search = null): Collection
    {
        return $this->repository->getCategories($companyId, ErpConstant::TYPE_ASSETS_BRAND, $search);
    }

    /**
     * Create a new Category.
     */
    public function createCategory(int $companyId, string $name)
    {
        return $this->repository->create([
            'company_id' => $companyId,
            'type' => ErpConstant::TYPE_ASSETS_CATEGORY,
            'category_name' => $name,
        ]);
    }

    /**
     * Create a new Brand.
     */
    public function createBrand(int $companyId, string $name)
    {
        return $this->repository->create([
            'company_id' => $companyId,
            'type' => ErpConstant::TYPE_ASSETS_BRAND,
            'category_name' => $name,
        ]);
    }

    /**
     * Update a Category.
     */
    public function updateCategory(int $companyId, int $id, string $name)
    {
        return $this->repository->update($id, $companyId, ErpConstant::TYPE_ASSETS_CATEGORY, [
            'category_name' => $name,
        ]);
    }

    /**
     * Update a Brand.
     */
    public function updateBrand(int $companyId, int $id, string $name)
    {
        return $this->repository->update($id, $companyId, ErpConstant::TYPE_ASSETS_BRAND, [
            'category_name' => $name,
        ]);
    }

    /**
     * Delete a Category.
     */
    public function deleteCategory(int $companyId, int $id): bool
    {
        return $this->repository->delete($id, $companyId, ErpConstant::TYPE_ASSETS_CATEGORY);
    }

    /**
     * Delete a Brand.
     */
    public function deleteBrand(int $companyId, int $id): bool
    {
        return $this->repository->delete($id, $companyId, ErpConstant::TYPE_ASSETS_BRAND);
    }
}
