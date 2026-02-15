<?php

namespace App\Repository\Interface\Recruitment;

use App\Models\Job;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface JobRepositoryInterface
{
    public function getAll(array $filters = []): mixed;
    public function getActiveJobs(int $companyId): Collection;
    public function getById(int $id): ?Job;
    public function create(array $data): Job;
    public function update(Job $job, array $data): bool;
    public function delete(Job $job): bool;
    public function addCandidate(array $data): \App\Models\Candidate;
    public function hasApplied(int $jobId, int $staffId): bool;
}
