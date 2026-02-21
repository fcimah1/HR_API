<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Models\EmployeeOnetimeLeave;

interface EmployeeOnetimeLeaveRepositoryInterface
{
    public function hasUsed(int $employeeId, string $leaveType): bool;

    public function create(array $data): EmployeeOnetimeLeave;

    public function getEmployeeOneTimeLeaves(int $employeeId): array;
}
