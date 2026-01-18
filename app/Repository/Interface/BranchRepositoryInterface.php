<?php

namespace App\Repository\Interface;

use Illuminate\Support\Collection;

interface BranchRepositoryInterface
{
    public function getAllBranches(int $companyId, array $filters = []): Collection;
    public function getBranchById(int $id, int $companyId);
    //get branch by company
    
}
