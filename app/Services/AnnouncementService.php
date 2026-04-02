<?php

namespace App\Services;

use App\Repository\Interface\AnnouncementRepositoryInterface;
use App\DTOs\Announcement\AnnouncementFilterDTO;
use App\DTOs\Announcement\CreateAnnouncementDTO;
use App\DTOs\Announcement\UpdateAnnouncementDTO;
use App\DTOs\Announcement\AnnouncementResponseDTO;
use App\Models\Announcement;

class AnnouncementService
{
    public function __construct(
        private readonly AnnouncementRepositoryInterface $announcementRepository
    ) {}

    public function getAllAnnouncements(AnnouncementFilterDTO $filters): mixed
    {
        return $this->announcementRepository->getAll($filters);
    }

    public function getAnnouncementById(int $id, int $companyId): ?Announcement
    {
        return $this->announcementRepository->getById($id, $companyId);
    }

    public function createAnnouncement(CreateAnnouncementDTO $dto): Announcement
    {
        return $this->announcementRepository->create($dto);
    }

    public function updateAnnouncement(int $id, int $companyId, UpdateAnnouncementDTO $dto): ?Announcement
    {
        $announcement = $this->announcementRepository->getById($id, $companyId);
        if (!$announcement) {
            return null;
        }

        return $this->announcementRepository->update($announcement, $dto);
    }

    public function deleteAnnouncement(int $id, int $companyId): bool
    {
        return $this->announcementRepository->delete($id, $companyId);
    }
}
