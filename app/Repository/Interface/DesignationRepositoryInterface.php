<?php

namespace App\Repository\Interface;

use App\Models\Designation;
use Illuminate\Support\Collection;

interface DesignationRepositoryInterface
{
    public function getAllDesignations(int $companyId, array $filters = []): mixed;
    public function getDesignationById(int $id, int $companyId);
    public function create(array $data): Designation;
    public function update(Designation $designation, array $data): Designation;
    public function delete(int $id, int $companyId): bool;
}
