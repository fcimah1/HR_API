<?php

namespace App\Services;

use App\Repository\Interface\BranchRepositoryInterface;

class BranchService
{
    protected $branchRepository;

    public function __construct(BranchRepositoryInterface $branchRepository)
    {
        $this->branchRepository = $branchRepository;
    }

    public function getBranches(int $companyId, array $filters = [])
    {
        return $this->branchRepository->getAllBranches($companyId, $filters);
    }

    public function getBranch(int $id, int $companyId)
    {
        return $this->branchRepository->getBranchById($id, $companyId);
    }
}
