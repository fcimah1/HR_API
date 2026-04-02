<?php

namespace App\Repository;

use App\Models\Announcement;
use App\Repository\Interface\AnnouncementRepositoryInterface;
use App\DTOs\Announcement\AnnouncementFilterDTO;
use App\DTOs\Announcement\CreateAnnouncementDTO;
use App\DTOs\Announcement\UpdateAnnouncementDTO;

class AnnouncementRepository implements AnnouncementRepositoryInterface
{
    public function getAll(AnnouncementFilterDTO $filters): mixed
    {
        $query = Announcement::with('publisher')
            ->forCompany($filters->companyId);

        if ($filters->search) {
            $query->where('title', 'LIKE', "%{$filters->search}%");
        }

        if ($filters->status !== null) {
            $query->where('is_active', $filters->status);
        }

        if ($filters->targetDepartmentId) {
            $query->where(function ($q) use ($filters) {
                $q->where('department_id', '0,all')
                    ->orWhereRaw("FIND_IN_SET(?, department_id)", [$filters->targetDepartmentId]);
            });
        }

        if ($filters->targetEmployeeId) {
            $query->where(function ($q) use ($filters) {
                $q->where('audience_id', '0,all')
                    ->orWhereRaw("FIND_IN_SET(?, audience_id)", [$filters->targetEmployeeId]);
            });
        }

        $query->orderBy('announcement_id', 'desc');

        if ($filters->paginate) {
            return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
        }

        return $query->get();
    }

    public function getById(int $id, int $companyId): ?Announcement
    {
        return Announcement::with('publisher')
            ->where('announcement_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    public function create(CreateAnnouncementDTO $dto): Announcement
    {
        $data = $dto->toArray();
        $data['created_at'] = now()->format('d-m-Y H:i:s');

        return Announcement::create($data);
    }

    public function update(Announcement $announcement, UpdateAnnouncementDTO $dto): Announcement
    {
        $announcement->update($dto->toArray());
        return $announcement;
    }

    public function delete(int $id, int $companyId): bool
    {
        $announcement = $this->getById($id, $companyId);
        if (!$announcement) {
            return false;
        }
        return (bool) $announcement->delete();
    }
}
