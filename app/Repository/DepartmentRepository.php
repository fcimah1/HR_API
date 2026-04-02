<?php

namespace App\Repository;

use App\Models\Department;
use App\Repository\Interface\DepartmentRepositoryInterface;
use Illuminate\Support\Collection;

class DepartmentRepository implements DepartmentRepositoryInterface
{
    public function getAllDepartments(int $companyId, array $filters = []): mixed
    {
        $query = Department::where('company_id', $companyId)->withCount('userDetails');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('department_name', 'LIKE', "%{$search}%");
        }

        $query->orderBy('department_name', 'asc');

        if (isset($filters['paginate']) && (bool)$filters['paginate'] === true) {
            $perPage = $filters['per_page'] ?? 10;
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    public function getDepartmentById(int $id, int $companyId)
    {
        return Department::where('department_id', $id)
            ->where('company_id', $companyId)
            ->withCount('userDetails')
            ->first();
    }

    public function create(array $data): Department
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = now()->toDateTimeString();
        }
        return Department::create($data);
    }

    public function update(Department $department, array $data): Department
    {
        $department->update($data);
        return $department->refresh()->loadCount('userDetails');
    }

    public function delete(int $id, int $companyId): bool
    {
        $department = $this->getDepartmentById($id, $companyId);
        if ($department) {
            return $department->delete();
        }
        return false;
    }
}
