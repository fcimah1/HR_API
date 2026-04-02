<?php

namespace App\Repository;

use App\Models\ErpConstant;
use App\Repository\Interface\AssetConfigurationRepositoryInterface;

class AssetConfigurationRepository implements AssetConfigurationRepositoryInterface
{
    /**
     * Get all asset categories/brands for a company (with search support).
     */
    public function getCategories(int $companyId, string $type, ?string $search = null)
    {
        return ErpConstant::where('company_id', $companyId)
            ->where('type', $type)
            ->when($search, function ($query) use ($search) {
                $query->where('category_name', 'like', "%{$search}%");
            })
            ->select('constants_id', 'category_name', 'created_at')
            ->get();
    }

    /**
     * Create a new configuration (Category or Brand).
     */
    public function create(array $data)
    {
        $config = new ErpConstant();
        $config->company_id = $data['company_id'];
        $config->type = $data['type'];
        $config->category_name = $data['category_name'];
        $config->created_at = now();
        $config->save();

        return $config;
    }

    /**
     * Find a configuration by ID, Type, and Company.
     */
    public function find(int $id, int $companyId, string $type)
    {
        return ErpConstant::where('company_id', $companyId)
            ->where('type', $type)
            ->where('constants_id', $id)
            ->first();
    }

    /**
     * Update a configuration.
     */
    public function update(int $id, int $companyId, string $type, array $data)
    {
        $config = $this->find($id, $companyId, $type);

        if ($config) {
            $config->category_name = $data['category_name'];
            $config->save();
            return $config;
        }

        return null;
    }

    /**
     * Delete a configuration by ID, Type, and Company.
     */
    public function delete(int $id, int $companyId, string $type)
    {
        $config = $this->find($id, $companyId, $type);

        if ($config) {
            return $config->delete();
        }

        return false;
    }
}
