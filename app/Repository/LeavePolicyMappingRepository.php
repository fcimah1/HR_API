<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\LeavePolicyMapping;
use App\Repository\Interface\LeavePolicyMappingRepositoryInterface;

class LeavePolicyMappingRepository implements LeavePolicyMappingRepositoryInterface
{
    /**
     * Get system leave type for a given leave type ID
     */
    public function getSystemLeaveType(int $companyId, int $leaveTypeId): ?string
    {
        return LeavePolicyMapping::getSystemLeaveType($companyId, $leaveTypeId);
    }

    /**
     * Get all mappings for a company
     */
    public function getCompanyMappings(int $companyId): array
    {
        return LeavePolicyMapping::forCompany($companyId)->get()->toArray();
    }

    /**
     * Get system default mappings
     */
    public function getSystemDefaultMappings(): array
    {
        return LeavePolicyMapping::systemDefaults()->get()->toArray();
    }

    /**
     * Delete all mappings for a company
     */
    public function deleteByCompanyId(int $companyId): int
    {
        return LeavePolicyMapping::where('company_id', $companyId)->delete();
    }

    /**
     * Create a new mapping
     */
    public function create(array $data): LeavePolicyMapping
    {
        return LeavePolicyMapping::create($data);
    }
}
