<?php

namespace App\Services\Recruitment;

use App\Models\Candidate;
use App\Repository\Interface\Recruitment\CandidateRepositoryInterface;
use App\Services\FileUploadService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CandidateService
{
    public function __construct(
        protected CandidateRepositoryInterface $candidateRepository,
        protected FileUploadService $fileUploadService
    ) {}

    public function getCandidates(array $filters = []): mixed
    {
        return $this->candidateRepository->getAll($filters);
    }

    public function getCandidate(int $id, int $companyId): ?Candidate
    {
        return $this->candidateRepository->getById($id, $companyId);
    }

    public function deleteCandidate(int $id, int $companyId): bool
    {
        $candidate = $this->candidateRepository->getById($id, $companyId);
        if (!$candidate) {
            return false;
        }
        return $this->candidateRepository->delete($candidate);
    }

    public function updateCandidateStatus(int $id, int $companyId, array $data): Candidate
    {
        $candidate = $this->candidateRepository->getById($id, $companyId);
        if (!$candidate) {
            throw new \Exception('المرشح غير موجود');
        }

        // Check if current status is PENDING (0)
        if ($candidate->application_status !== \App\Enums\Recruitment\CandidateStatusEnum::PENDING) {
            $message = $candidate->application_status === \App\Enums\Recruitment\CandidateStatusEnum::REJECTED
                ? 'تم تحديث الحالة سابقا بالرفض'
                : 'تم تحديث الحالة سابقا بالموافقة على المقابلة';
            throw new \Exception('لا يمكن تحديث حالة المرشح :' . ' ' . $message);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($candidate, $data) {
            // 1. Update Candidate Status and Remarks
            $updated = $this->candidateRepository->update($candidate, [
                'application_status' => $data['application_status'],
                'application_remarks' => $data['application_remarks'] ?? $candidate->application_remarks,
            ]);

            if (!$updated) {
                throw new \Exception('فشل حفظ حالة المرشح في قاعدة البيانات');
            }

            // 2. If status is "Invited to Interview" (status value 1), create an interview record
            if ((int)$data['application_status'] === \App\Enums\Recruitment\CandidateStatusEnum::INVITED_TO_INTERVIEW->value) {
                if (isset($data['interview_date']) && !empty($data['interview_date'])) {
                    \App\Models\Interview::create([
                        'company_id' => $candidate->company_id,
                        'job_id' => $candidate->job_id,
                        'designation_id' => $candidate->designation_id,
                        'staff_id' => $candidate->staff_id,
                        'interview_place' => $data['interview_place'] ?? null,
                        'interview_date' => $data['interview_date'],
                        'interview_time' => $data['interview_time'] ?? null,
                        'interviewer_id' => $data['interviewer_id'] ?? null,
                        'description' => $data['description'] ?? null,
                        'status' => 0, // Pending interview
                    ]);
                }
            }

            return $candidate->refresh();
        });
    }

    public function downloadCandidateResume(int $id, int $companyId)
    {
        $candidate = $this->candidateRepository->getById($id, $companyId);
        if (!$candidate || !$candidate->job_resume) {
            Log::error('Candidate resume not found: ' . $id, [
                'user_id' => Auth::id(),
                'company_id' => $companyId,
                'candidate_id' => $id,
            ]);
            throw new \Exception('السيرة الذاتية غير موجودة');
        }

        // Logic to get the absolute path
        // Based on FileUploadService, files are stored in env('SHARED_UPLOADS_PATH')
        $basePath = env('SHARED_UPLOADS_PATH');
        // Candidate model accessor prepends 'candidates/' to job_resume
        // We should get the raw value
        $rawResume = $candidate->getRawOriginal('job_resume');
        $filePath = $basePath . '/candidates/' . $rawResume;

        if (!file_exists($filePath)) {
            // Check if it's already a full path
            if (file_exists($rawResume)) {
                $filePath = $rawResume;
            } else {
                Log::error('Candidate resume not found: ' . $id, [
                    'user_id' => Auth::id(),
                    'company_id' => $companyId,
                    'candidate_id' => $id,
                ]);
                throw new \Exception('الملف غير موجود على الخادم');
            }
        }

        return $filePath;
    }
}
