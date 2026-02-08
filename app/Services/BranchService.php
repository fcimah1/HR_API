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

    public function getBranches(int $companyId, array $filters = []): mixed
    {
        return $this->branchRepository->getAllBranches($companyId, $filters);
    }

    public function getBranch(int $id, int $companyId)
    {
        return $this->branchRepository->getBranchById($id, $companyId);
    }

    public function createBranch(\App\DTOs\Branch\CreateBranchDTO $dto)
    {
        return $this->branchRepository->create($dto);
    }

    public function updateBranch(int $id, int $companyId, \App\DTOs\Branch\UpdateBranchDTO $dto)
    {
        $branch = $this->branchRepository->getBranchById($id, $companyId);
        if (!$branch) {
            throw new \Exception('الفرع غير موجود');
        }
        return $this->branchRepository->update($branch, $dto);
    }

    public function deleteBranch(int $id, int $companyId)
    {
        return $this->branchRepository->delete($id, $companyId);
    }
}
