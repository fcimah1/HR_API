<?php

declare(strict_types=1);

namespace App\Repository\Interface;

interface LeavePolicyMappingRepositoryInterface
{
    public function getSystemLeaveType(int $companyId, int $leaveTypeId): ?string;

    public function getCompanyMappings(int $companyId): array;

    public function getSystemDefaultMappings(): array;
}
