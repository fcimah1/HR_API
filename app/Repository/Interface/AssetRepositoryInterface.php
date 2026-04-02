<?php

namespace App\Repository\Interface;

use App\Models\Asset;
use Illuminate\Pagination\LengthAwarePaginator;

interface AssetRepositoryInterface
{
    /**
     * Get Assets with filters and pagination.
     */
    public function getAssets(int $companyId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Create a new Asset.
     */
    public function create(array $data): Asset;

    /**
     * Find an Asset by ID and Company.
     */
    public function find(int $id, int $companyId): ?Asset;

    /**
     * Update an Asset.
     */
    public function update(Asset $asset, array $data): Asset;

    /**
     * Delete an Asset.
     */
    public function delete(Asset $asset): bool;
}
