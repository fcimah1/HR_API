<?php

namespace App\Repository\Interface;

use Illuminate\Support\Collection;

interface BranchRepositoryInterface
{
    public function getAllBranches(int $companyId, array $filters = []): mixed;
    public function getBranchById(int $id, int $companyId);
    public function create(\App\DTOs\Branch\CreateBranchDTO $dto): \App\Models\Branch;
    public function update(\App\Models\Branch $branch, \App\DTOs\Branch\UpdateBranchDTO $dto): \App\Models\Branch;
    public function delete(int $id, int $companyId): bool;
}
