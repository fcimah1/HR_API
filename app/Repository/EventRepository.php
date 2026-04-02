<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Event;
use App\Models\User;
use App\DTOs\Event\EventFilterDTO;
use App\Repository\Interface\EventRepositoryInterface;

class EventRepository implements EventRepositoryInterface
{
    public function __construct(
        protected \App\Services\SimplePermissionService $permissionService
    ) {}

    public function getPaginatedEvents(EventFilterDTO $filters): array
    {
        $query = Event::query()
            ->where('company_id', $filters->companyId);

        // Hierarchical filtering: Only show events where at least one employee is a subordinate (or self)
        if (!$this->permissionService->isCompanyOwner($filters->requester)) {
            $subordinates = $this->permissionService->getEmployeesByHierarchy(
                $filters->requester->user_id,
                $filters->companyId,
                true // Include self
            );
            $subordinateIds = array_column($subordinates, 'user_id');

            if (!empty($subordinateIds)) {
                $query->where(function ($q) use ($subordinateIds) {
                    foreach ($subordinateIds as $id) {
                        $q->orWhereRaw("FIND_IN_SET(?, employee_id)", [$id]);
                    }
                });
            } else {
                // No subordinates and not owner? Should only see their own events if they are in employee_id
                $query->whereRaw("FIND_IN_SET(?, employee_id)", [$filters->requester->user_id]);
            }
        }

        if (!empty($filters->search)) {
            $query->where(function ($q) use ($filters) {
                $q->where('event_title', 'like', '%' . $filters->search . '%')
                    ->orWhere('event_note', 'like', '%' . $filters->search . '%');
            });
        }

        $paginator = $query->orderBy('event_date', 'desc')
            ->orderBy('event_time', 'desc')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }

    public function findById(int $id, int $companyId): ?Event
    {
        return Event::where('event_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    public function hasEventAccess(Event $event, User $user): bool
    {
        if ($this->permissionService->isCompanyOwner($user)) {
            return true;
        }

        $subordinates = $this->permissionService->getEmployeesByHierarchy(
            $user->user_id,
            $event->company_id,
            true // Include self
        );
        $subordinateIds = array_column($subordinates, 'user_id');

        if (empty($subordinateIds)) {
            // No subordinates, only check if user is in employee_id
            $employeeIds = explode(',', (string) $event->employee_id);
            return in_array((string) $user->user_id, $employeeIds);
        }

        // Check if any employee in the event is a subordinate (or self)
        $eventEmployeeIds = explode(',', (string) $event->employee_id);
        foreach ($eventEmployeeIds as $eid) {
            if (in_array((int) $eid, $subordinateIds)) {
                return true;
            }
        }

        return false;
    }

    public function create(array $data): Event
    {
        return Event::create($data);
    }

    public function update(int $id, array $data): bool
    {
        return (bool) Event::where('event_id', $id)->update($data);
    }

    public function delete(int $id): bool
    {
        return (bool) Event::where('event_id', $id)->delete();
    }
}
