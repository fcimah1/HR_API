<?php

namespace App\Services\Recruitment;

use App\Models\Interview;
use App\Repository\Interface\Recruitment\InterviewRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class InterviewService
{
    public function __construct(
        protected InterviewRepositoryInterface $interviewRepository
    ) {}

    public function getInterviews(array $filters = []): mixed
    {
        return $this->interviewRepository->getAll($filters);
    }

    public function getInterview(int $id, int $companyId): ?Interview
    {
        return $this->interviewRepository->getById($id, $companyId);
    }

    public function updateInterviewStatus(int $id, int $companyId, array $data): Interview
    {
        $interview = $this->interviewRepository->getById($id, $companyId);
        if (!$interview) {
            Log::error('Interview not found', [
                'interview_id' => $id,
                'company_id' => $companyId,
            ]);
            throw new \Exception('المقابلة غير موجودة');
        }

        // Restriction: Update only if status is NOT_STARTED (0)
        if ($interview->status !== \App\Enums\Recruitment\InterviewStatusEnum::NOT_STARTED) {
            $message = $interview->status === \App\Enums\Recruitment\InterviewStatusEnum::SUCCESSFUL
                ? 'تم التحديث سابقا بمقابلة ناجحة'
                : 'تم التحديث سابقا بالرفض';
            throw new \Exception('لا يمكن تحديث حالة المقابلة : ' . $message);
        }

        $this->interviewRepository->update($interview, $data);

        return $interview->refresh();
    }
}
