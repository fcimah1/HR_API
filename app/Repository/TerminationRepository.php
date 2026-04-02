<?php

namespace App\Repository;

use App\Models\Termination;
use App\Repository\Interface\TerminationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TerminationRepository implements TerminationRepositoryInterface
{
    public function getTerminations(int $companyId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Termination::query()
            ->where('company_id', $companyId)
            ->with(['employee:user_id,first_name,last_name,profile_photo']);

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['search'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('last_name', 'like', '%' . $filters['search'] . '%');
            })->orWhere('reason', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('termination_id', 'desc')->paginate($perPage);
    }

    public function create(array $data): Termination
    {
        return Termination::create($data);
    }

    public function update(Termination $termination, array $data): Termination
    {
        $termination->update($data);
        return $termination->fresh();
    }

    public function delete(Termination $termination): bool
    {
        return $termination->delete();
    }

    public function find(int $id, int $companyId): ?Termination
    {
        return Termination::where('company_id', $companyId)
            ->with(['employee:user_id,first_name,last_name,profile_photo'])
            ->find($id);
    }
}
