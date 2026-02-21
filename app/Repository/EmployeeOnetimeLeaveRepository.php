<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\EmployeeOnetimeLeave;
use App\Repository\Interface\EmployeeOnetimeLeaveRepositoryInterface;

class EmployeeOnetimeLeaveRepository implements EmployeeOnetimeLeaveRepositoryInterface
{
    /**
     * Check if employee has used a one-time leave
     */
    public function hasUsed(int $employeeId, string $leaveType): bool
    {
        return EmployeeOnetimeLeave::hasUsed($employeeId, $leaveType);
    }

    /**
     * Create a one-time leave record
     */
    public function create(array $data): EmployeeOnetimeLeave
    {
        return EmployeeOnetimeLeave::create($data);
    }

    /**
     * Get all one-time leaves for an employee
     */
    public function getEmployeeOneTimeLeaves(int $employeeId): array
    {
        return EmployeeOnetimeLeave::forEmployee($employeeId)->get()->toArray();
    }
}
