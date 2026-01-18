<?php

namespace App\Repository;

use App\Models\Branch;
use App\Repository\Interface\BranchRepositoryInterface;
use Illuminate\Support\Collection;

class BranchRepository implements BranchRepositoryInterface
{
    public function getAllBranches(int $companyId, array $filters = []): Collection
    {
        $query = Branch::where('company_id', $companyId);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('branch_name', 'LIKE', "%{$search}%");
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        return $query->orderBy('branch_name', 'asc')->get();
    }

    public function getBranchById(int $id, int $companyId)
    {
        return Branch::where('branch_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }
}
