<?php

namespace App\Repository\Interface;

use App\Models\Department;
use Illuminate\Support\Collection;

interface DepartmentRepositoryInterface
{
    public function getAllDepartments(int $companyId, array $filters = []): mixed;
    public function getDepartmentById(int $id, int $companyId);
    public function create(array $data): Department;
    public function update(Department $department, array $data): Department;
    public function delete(int $id, int $companyId): bool;
}
