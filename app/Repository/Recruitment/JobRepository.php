<?php

namespace App\Repository\Recruitment;

use App\Models\Job;
use App\Enums\Recruitment\JobStatusEnum;
use App\Repository\Interface\Recruitment\JobRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class JobRepository implements JobRepositoryInterface
{
    public function getAll(array $filters = []): mixed
    {
        $query = Job::query()->with('designation');

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['designation_id'])) {
            $query->where('designation_id', $filters['designation_id']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('job_title', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('short_description', 'like', '%' . $filters['search'] . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 10;
        $paginate = $filters['paginate'] ?? true;

        if ($paginate) {
            return $query->orderBy('created_at', 'desc')->paginate($perPage);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getActiveJobs(int $companyId): Collection
    {
        return Job::where('company_id', $companyId)
            ->where('status', JobStatusEnum::PUBLISHED)
            ->where('date_of_closing', '>=', now())
            ->get();
    }

    public function getById(int $id): ?Job
    {
        return Job::with('designation')->where('job_id', $id)->first();
    }

    public function create(array $data): Job
    {
        return Job::create($data);
    }

    public function update(Job $job, array $data): bool
    {
        return $job->update($data);
    }

    public function delete(Job $job): bool
    {
        return $job->update([
            'status' => JobStatusEnum::UNPUBLISHED,
            'date_of_closing' => now()->format('Y-m-d'),
        ]);
    }

    public function addCandidate(array $data): \App\Models\Candidate
    {
        // Provide defaults for required DB fields without defaults
        $data['application_status'] = $data['application_status'] ?? \App\Enums\Recruitment\CandidateStatusEnum::PENDING->value;
        $data['application_remarks'] = $data['application_remarks'] ?? '';

        return \App\Models\Candidate::create($data);
    }

    public function hasApplied(int $jobId, int $staffId): bool
    {
        return \App\Models\Candidate::where('job_id', $jobId)
            ->where('staff_id', $staffId)
            ->exists();
    }
}
