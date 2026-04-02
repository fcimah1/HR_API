<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\DTOs\Event\CreateEventDTO;
use App\DTOs\Event\UpdateEventDTO;
use App\DTOs\Event\EventFilterDTO;
use App\Repository\Interface\EventRepositoryInterface;
use Illuminate\Support\Facades\Log;

class EventService
{
    public function __construct(
        protected EventRepositoryInterface $repository
    ) {}

    public function getPaginatedEvents(EventFilterDTO $dto): array
    {
        return $this->repository->getPaginatedEvents($dto);
    }

    public function getEventById(int $id, int $companyId, ?User $requester = null): ?Event
    {
        $event = $this->repository->findById($id, $companyId);
          if (!$event) {
            throw new \Exception('الحدث غير موجود', 404);
        }
        if ($event && $requester) {
            if (!$this->repository->hasEventAccess($event, $requester)) {
                throw new \Exception('غير مصرح لك بعرض هذا الحدث', 403);
            }
        }
        return $event;
    }

    public function createEvent(CreateEventDTO $dto): Event
    {
        return $this->repository->create($dto->toArray());
    }

    public function updateEvent(int $id, int $companyId, UpdateEventDTO $dto, ?User $requester = null): ?Event
    {
        $event = $this->repository->findById($id, $companyId);
        if (!$event) {
            throw new \Exception('الحدث غير موجود', 404);
        }

        if ($requester && !$this->repository->hasEventAccess($event, $requester)) {
            throw new \Exception('غير مصرح لك بتعديل هذا الحدث', 403);
        }

        $this->repository->update($id, $dto->toArray());
        return $this->repository->findById($id, $companyId);
    }

    public function deleteEvent(int $id, int $companyId, ?User $requester = null): bool
    {
        $event = $this->repository->findById($id, $companyId);
        if (!$event) {
            throw new \Exception('الحدث غير موجود', 404);
        }

        if ($requester && !$this->repository->hasEventAccess($event, $requester)) {
            throw new \Exception('غير مصرح لك بحذف هذا الحدث', 403);
        }

        return $this->repository->delete($id);
    }
}
