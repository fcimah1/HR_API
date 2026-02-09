<?php

namespace App\Repository\Interface;

use App\Models\Announcement;
use App\DTOs\Announcement\AnnouncementFilterDTO;
use App\DTOs\Announcement\CreateAnnouncementDTO;
use App\DTOs\Announcement\UpdateAnnouncementDTO;

interface AnnouncementRepositoryInterface
{
    public function getAll(AnnouncementFilterDTO $filters): mixed;
    public function getById(int $id, int $companyId): ?Announcement;
    public function create(CreateAnnouncementDTO $dto): Announcement;
    public function update(Announcement $announcement, UpdateAnnouncementDTO $dto): Announcement;
    public function delete(int $id, int $companyId): bool;
}
