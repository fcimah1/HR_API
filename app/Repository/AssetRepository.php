<?php

namespace App\Repository;

use App\Models\Asset;
use App\Repository\Interface\AssetRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AssetRepository implements AssetRepositoryInterface
{
    /**
     * Get Assets with filters and pagination.
     */
    public function getAssets(int $companyId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Asset::query()
            ->where('company_id', $companyId)
            ->with(['category', 'brand', 'employee']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_asset_code', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['asset_status'])) {
            if ($filters['asset_status'] === 'working') {
                $query->working();
            } // Add other statuses if implemented in model scopes
        }

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('assets_category_id', $filters['category_id']);
        }

        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Create a new Asset.
     */
    public function create(array $data): Asset
    {
        return Asset::create($data);
    }

    /**
     * Find an Asset by ID and Company.
     */
    public function find(int $id, int $companyId): ?Asset
    {
        return Asset::where('assets_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Update an Asset.
     */
    public function update(Asset $asset, array $data): Asset
    {
        $asset->update($data);
        return $asset;
    }

    /**
     * Delete an Asset.
     */
    public function delete(Asset $asset): bool
    {
        return $asset->delete();
    }
}
