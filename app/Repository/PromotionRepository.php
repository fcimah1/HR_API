<?php

namespace App\Repository;

use App\Models\Promotion;
use App\Repository\Interface\PromotionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PromotionRepository implements PromotionRepositoryInterface
{
    public function getPromotions(int $companyId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Promotion::query()
            ->forCompany($companyId)
            ->with([
                'employee:user_id,first_name,last_name,profile_photo',
                'oldDepartment:department_id,department_name',
                'newDepartment:department_id,department_name',
                'oldDesignation:designation_id,designation_name',
                'newDesignation:designation_id,designation_name'
            ]);

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['search'])) {
            $query->where('promotion_title', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('promotion_id', 'desc')->paginate($perPage);
    }

    public function create(array $data): Promotion
    {
        return Promotion::create($data);
    }

    public function update(Promotion $promotion, array $data): Promotion
    {
        $promotion->update($data);
        return $promotion->fresh();
    }

    public function delete(Promotion $promotion): bool
    {
        return $promotion->delete();
    }

    public function find(int $id, int $companyId): ?Promotion
    {
        return Promotion::where('company_id', $companyId)
            ->with([
                'employee:user_id,first_name,last_name,profile_photo',
                'oldDepartment:department_id,department_name',
                'newDepartment:department_id,department_name',
                'oldDesignation:designation_id,designation_name',
                'newDesignation:designation_id,designation_name'
            ])
            ->find($id);
    }
}
