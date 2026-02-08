<?php

namespace App\Repository;

use App\Models\Designation;
use App\Repository\Interface\DesignationRepositoryInterface;
use Illuminate\Support\Collection;

class DesignationRepository implements DesignationRepositoryInterface
{
    public function getAllDesignations(int $companyId, array $filters = []): mixed
    {
        $query = Designation::where('company_id', $companyId)
            ->with('department')
            ->withCount('userDetails');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('designation_name', 'LIKE', "%{$search}%");
        }

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        $query->orderBy('designation_name', 'asc');

        if (isset($filters['paginate']) && (bool)$filters['paginate'] === true) {
            $perPage = $filters['per_page'] ?? 10;
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    public function getDesignationById(int $id, int $companyId)
    {
        return Designation::where('designation_id', $id)
            ->where('company_id', $companyId)
            ->with('department')
            ->withCount('userDetails')
            ->first();
    }

    public function create(array $data): Designation
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = now()->toDateTimeString();
        }
        return Designation::create($data);
    }

    public function update(Designation $designation, array $data): Designation
    {
        $designation->update($data);
        return $designation->refresh()->load('department')->loadCount('userDetails');
    }

    public function delete(int $id, int $companyId): bool
    {
        $designation = $this->getDesignationById($id, $companyId);
        if ($designation) {
            return $designation->delete();
        }
        return false;
    }
}
