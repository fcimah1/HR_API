<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Meeting;
use App\Repository\Interface\MeetingRepositoryInterface;
use App\DTOs\Meeting\MeetingFilterDTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MeetingRepository implements MeetingRepositoryInterface
{
    public function findById(int $id, int $companyId): ?Meeting
    {
        return Meeting::where('meeting_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    public function getAll(MeetingFilterDTO $filters, int $companyId): Collection|LengthAwarePaginator
    {
        $query = Meeting::where('company_id', $companyId);

        if ($filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('meeting_title', 'like', '%' . $filters->search . '%')
                    ->orWhere('meeting_note', 'like', '%' . $filters->search . '%')
                    ->orWhere('meeting_room', 'like', '%' . $filters->search . '%');
            });
        }

        if ($filters->date) {
            $query->where('meeting_date', $filters->date);
        }

        $query->orderBy('meeting_id', 'desc');

        return $filters->paginate
            ? $query->paginate($filters->perPage)
            : $query->get();
    }

    public function create(array $data): Meeting
    {
        return Meeting::create($data);
    }

    public function update(Meeting $meeting, array $data): bool
    {
        return $meeting->update($data);
    }

    public function delete(Meeting $meeting): bool
    {
        return $meeting->delete();
    }
}
