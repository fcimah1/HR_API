<?php

namespace App\Services;

use App\Repository\Interface\DepartmentRepositoryInterface;
use App\Models\Department;
use Illuminate\Support\Collection;

class DepartmentService
{
    public function __construct(
        protected DepartmentRepositoryInterface $departmentRepository
    ) {}

    public function getDepartments(int $companyId, array $filters = []): mixed
    {
        return $this->departmentRepository->getAllDepartments($companyId, $filters);
    }

    public function getDepartment(int $id, int $companyId): ?Department
    {
        return $this->departmentRepository->getDepartmentById($id, $companyId);
    }

    public function createDepartment(array $data): Department
    {
        return $this->departmentRepository->create($data);
    }

    public function updateDepartment(int $id, int $companyId, array $data): ?Department
    {
        $department = $this->getDepartment($id, $companyId);
        if (!$department) {
            return null;
        }
        return $this->departmentRepository->update($department, $data);
    }

    public function deleteDepartment(int $id, int $companyId): bool
    {
        return $this->departmentRepository->delete($id, $companyId);
    }
}
