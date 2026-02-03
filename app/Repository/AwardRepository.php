<?php

namespace App\Repository;

use App\Models\Award;
use App\Repository\Interface\AwardRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AwardRepository implements AwardRepositoryInterface
{
    public function getAwards(int $companyId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Award::query()->forCompany($companyId);

        // Eager load relations
        $query->with(['employee:user_id,first_name,last_name,profile_photo', 'awardType:constants_id,category_name']);

        // Check if employee_id filter is present
        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        // Check if award_type_id filter is present
        if (isset($filters['award_type_id'])) {
            $query->where('award_type_id', $filters['award_type_id']);
        }

        return $query->orderBy('award_id', 'desc')->paginate($perPage);
    }

    public function create(array $data): Award
    {
        return Award::create($data);
    }

    public function update(Award $award, array $data): Award
    {
        $award->update($data);
        return $award;
    }

    public function delete(Award $award): bool
    {
        return $award->delete();
    }

    public function find(int $id, int $companyId): ?Award
    {
        return Award::forCompany($companyId)
            ->with(['employee:user_id,first_name,last_name,profile_photo', 'awardType:constants_id,category_name'])
            ->find($id);
    }
}
