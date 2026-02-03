<?php

namespace App\Repository\Interface;

interface AssetConfigurationRepositoryInterface
{
    /**
     * Get all asset categories for a company (scoping by type).
     */
    public function getCategories(int $companyId, string $type);

    /**
     * Create a new configuration (Category or Brand).
     */
    public function create(array $data);

    /**
     * Find a configuration by ID, Type, and Company.
     */
    public function find(int $id, int $companyId, string $type);

    /**
     * Update a configuration.
     */
    public function update(int $id, int $companyId, string $type, array $data);

    /**
     * Delete a configuration by ID, Type, and Company.
     */
    public function delete(int $id, int $companyId, string $type);
}
