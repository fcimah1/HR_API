<?php

namespace App\Repository\Interface;

use App\Models\User;
use App\Models\UserDetails;

interface UserRepositoryInterface
{
    /**
     * Get all subordinate employee IDs using recursive reporting structure
     * Filters by same department and lower hierarchy levels
     * 
     * @param User $manager
     * @return array
     */
    public function getSubordinateEmployeeIds(User $manager);

    public function getUserByCompositeKey(int $companyId, int $branchId, string $employeeId): ?UserDetails;

}
