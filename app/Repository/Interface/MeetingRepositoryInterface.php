<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Models\Meeting;
use App\DTOs\Meeting\MeetingFilterDTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MeetingRepositoryInterface
{
    public function findById(int $id, int $companyId): ?Meeting;
    public function getAll(MeetingFilterDTO $filters, int $companyId): Collection|LengthAwarePaginator;
    public function create(array $data): Meeting;
    public function update(Meeting $meeting, array $data): bool;
    public function delete(Meeting $meeting): bool;
}
