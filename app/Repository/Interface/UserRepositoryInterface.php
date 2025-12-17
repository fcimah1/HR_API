<?php

namespace App\Repository\Interface;

use App\Models\UserDetails;

interface UserRepositoryInterface
{
    /**
     * Get all subordinate employee IDs using recursive reporting structure
     * Filters by same department and lower hierarchy levels
     * 
     * @param int $managerId
     * @return array
     */
    public function getSubordinateEmployeeIds(int $managerId): array;

    public function getUserByCompositeKey(int $companyId, int $branchId, string $employeeIdnum): ?UserDetails;
}
