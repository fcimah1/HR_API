<?php

namespace App\Repository;

use App\Models\ResidenceRenewalCost;
use App\Repository\Interface\ResidenceRenewalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ResidenceRenewalRepository implements ResidenceRenewalRepositoryInterface
{
    public function getRenewals(int $companyId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ResidenceRenewalCost::query()
            ->where('company_id', $companyId)
            ->with(['employee:user_id,first_name,last_name,profile_photo']);

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['search'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('last_name', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('renewal_cost_id', 'desc')->paginate($perPage);
    }

    public function create(array $data): ResidenceRenewalCost
    {
        return ResidenceRenewalCost::create($data);
    }

    public function delete(ResidenceRenewalCost $renewal): bool
    {
        return $renewal->delete();
    }

    public function find(int $id, int $companyId): ?ResidenceRenewalCost
    {
        return ResidenceRenewalCost::where('company_id', $companyId)
            ->with(['employee:user_id,first_name,last_name,profile_photo'])
            ->find($id);
    }
}
