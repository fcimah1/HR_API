<?php

namespace App\Services\Recruitment;

use App\DTOs\Recruitment\Job\CreateJobDTO;
use App\DTOs\Recruitment\Job\UpdateJobDTO;
use App\Models\Job;
use App\Repository\Interface\Recruitment\JobRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class JobService
{
    public function __construct(
        protected JobRepositoryInterface $jobRepository,
        protected \App\Services\FileUploadService $fileUploadService
    ) {}

    public function getJobs(array $filters = []): mixed
    {
        return $this->jobRepository->getAll($filters);
    }

    public function getActiveJobs(int $companyId): Collection
    {
        return $this->jobRepository->getActiveJobs($companyId);
    }

    public function getJob(int $id): ?Job
    {
        return $this->jobRepository->getById($id);
    }

    public function createJob(CreateJobDTO $dto): Job
    {
        return $this->jobRepository->create($dto->toArray());
    }

    public function updateJob(int $id, UpdateJobDTO $dto): ?Job
    {
        $job = $this->jobRepository->getById($id);
        if (!$job) {
            Log::error('Job not found', ['job_id' => $id]);
            return null;
        }

        $data = array_filter($dto->toArray(), fn($value) => !is_null($value));
        $this->jobRepository->update($job, $data);

        return $job->refresh();
    }

    public function deleteJob(int $id): bool
    {
        $job = $this->jobRepository->getById($id);
        if (!$job) {
            Log::error('Job not found', ['job_id' => $id]);
            return false;
        }
        return $this->jobRepository->delete($job);
    }

    public function applyToJob(\App\DTOs\Recruitment\Job\ApplyJobDTO $dto): \App\Models\Candidate
    {
        $job = $this->jobRepository->getById($dto->job_id);

        if (!$job || $job->status?->value !== \App\Enums\Recruitment\JobStatusEnum::PUBLISHED->value) {
            Log::error('Job not found', [
                'job_id' => $dto->job_id,
            ]);
            throw new \Exception('لا يمكن التقديم على هذه الوظيفة لأنها غير منشورة', 400);
        }

        // Check if already applied
        if ($this->jobRepository->hasApplied($dto->job_id, $dto->staff_id)) {
            Log::error('Job already applied', [
                'job_id' => $dto->job_id,
                'staff_id' => $dto->staff_id,
                'company_id' => $job->company_id
            ]);
            throw new \Exception('تقدمت بالفعل لهذه الوظيفة', 400);
        }

        $data = $dto->toArray();

        if ($dto->job_resume) {
            $uploadResult = $this->fileUploadService->uploadDocument(
                $dto->job_resume,
                $dto->staff_id ?? 0,
                'candidates',
                'resume'
            );
            $data['job_resume'] = $uploadResult['filename'];
        }

        return $this->jobRepository->addCandidate($data);
    }
}
