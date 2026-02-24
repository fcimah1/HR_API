<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Models\Event;
use App\Models\User;
use App\DTOs\Event\EventFilterDTO;

interface EventRepositoryInterface
{
    public function getPaginatedEvents(EventFilterDTO $filters): array;
    public function findById(int $id, int $companyId): ?Event;
    public function hasEventAccess(Event $event, User $user): bool;
    public function create(array $data): Event;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
