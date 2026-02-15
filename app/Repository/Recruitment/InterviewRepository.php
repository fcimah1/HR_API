<?php

namespace App\Repository\Recruitment;

use App\Models\Interview;
use App\Repository\Interface\Recruitment\InterviewRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class InterviewRepository implements InterviewRepositoryInterface
{
    public function getAll(array $filters = []): mixed
    {
        $query = Interview::query()->with(['job', 'candidate.staff', 'interviewer', 'staff']);

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['job_id'])) {
            $query->where('job_id', $filters['job_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            // Searching by interviewer
            $query->whereHas('interviewer', function ($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('last_name', 'like', '%' . $filters['search'] . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 10;
        $paginate = $filters['paginate'] ?? true;

        if ($paginate) {
            return $query->orderBy('interview_date', 'asc')->paginate($perPage);
        }

        return $query->orderBy('interview_date', 'asc')->get();
    }

    public function getById(int $id, int $companyId): ?Interview
    {
        return Interview::with(['job', 'candidate', 'interviewer'])->where('company_id', $companyId)->find($id);
    }

    public function update(Interview $interview, array $data): bool
    {
        return $interview->update($data);
    }
}
