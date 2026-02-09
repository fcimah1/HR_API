<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\Interface\MeetingRepositoryInterface;
use App\DTOs\Meeting\CreateMeetingDTO;
use App\DTOs\Meeting\UpdateMeetingDTO;
use App\DTOs\Meeting\MeetingFilterDTO;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class MeetingService
{
    public function __construct(
        private readonly MeetingRepositoryInterface $meetingRepository
    ) {}

    public function getMeetings(MeetingFilterDTO $filters, int $companyId): Collection|LengthAwarePaginator
    {
        return $this->meetingRepository->getAll($filters, $companyId);
    }

    public function getMeeting(int $id, int $companyId): Meeting
    {
        $meeting = $this->meetingRepository->findById($id, $companyId);
        if (!$meeting) {
            throw new Exception('الاجتماع غير موجود.');
        }
        return $meeting;
    }

    public function createMeeting(CreateMeetingDTO $dto): Meeting
    {
        return $this->meetingRepository->create($dto->toArray());
    }

    public function updateMeeting(int $id, int $companyId, UpdateMeetingDTO $dto): Meeting
    {
        $meeting = $this->getMeeting($id, $companyId);
        $this->meetingRepository->update($meeting, $dto->toArray());
        return $meeting->fresh();
    }

    public function deleteMeeting(int $id, int $companyId): bool
    {
        $meeting = $this->getMeeting($id, $companyId);
        return $this->meetingRepository->delete($meeting);
    }
}
