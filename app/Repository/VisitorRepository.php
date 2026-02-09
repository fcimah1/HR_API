<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Visitor;
use App\Repository\Interface\VisitorRepositoryInterface;
use App\DTOs\Visitor\VisitorFilterDTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VisitorRepository implements VisitorRepositoryInterface
{
    public function findById(int $id, int $companyId): ?Visitor
    {
        return Visitor::where('visitor_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    public function getAll(VisitorFilterDTO $filters, int $companyId): Collection|LengthAwarePaginator
    {
        $query = Visitor::where('company_id', $companyId);

        if ($filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('visitor_name', 'like', '%' . $filters->search . '%')
                    ->orWhere('visit_purpose', 'like', '%' . $filters->search . '%')
                    ->orWhere('phone', 'like', '%' . $filters->search . '%')
                    ->orWhere('email', 'like', '%' . $filters->search . '%');
            });
        }

        if ($filters->date) {
            $query->where('visit_date', $filters->date);
        }

        if ($filters->departmentId) {
            $query->where('department_id', $filters->departmentId);
        }

        $query->orderBy('visitor_id', 'desc');

        return $filters->paginate
            ? $query->paginate($filters->perPage)
            : $query->get();
    }

    public function create(array $data): Visitor
    {
        return Visitor::create($data);
    }

    public function update(Visitor $visitor, array $data): bool
    {
        return $visitor->update($data);
    }

    public function delete(Visitor $visitor): bool
    {
        return $visitor->delete();
    }
}
