<?php

namespace App\Repository\Recruitment;

use App\Models\Candidate;
use App\Repository\Interface\Recruitment\CandidateRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CandidateRepository implements CandidateRepositoryInterface
{
    public function getAll(array $filters = []): mixed
    {
        $query = Candidate::query()->with(['job', 'designation', 'staff']);

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['job_id'])) {
            $query->where('job_id', $filters['job_id']);
        }

        if (isset($filters['status'])) {
            $query->where('application_status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->whereHas('staff', function ($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 10;
        $paginate = $filters['paginate'] ?? true;

        if ($paginate) {
            return $query->orderBy('created_at', 'desc')->paginate($perPage);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getById(int $id, int $companyId): ?Candidate
    {
        return Candidate::with(['job', 'designation', 'staff'])
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function update(Candidate $candidate, array $data): bool
    {
        return $candidate->update($data);
    }

    public function delete(Candidate $candidate): bool
    {
        return $candidate->delete();
    }
}
