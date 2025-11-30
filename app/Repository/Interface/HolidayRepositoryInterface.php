<?php

namespace App\Repository\Interface;

use App\DTOs\Holiday\CreateHolidayDTO;
use App\DTOs\Holiday\UpdateHolidayDTO;
use App\Models\Holiday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface HolidayRepositoryInterface
{
    public function getAll(int $companyId, array $filters = []): LengthAwarePaginator;
    public function findById(int $id, int $companyId): ?Holiday;
    public function create(CreateHolidayDTO $dto): Holiday;
    public function update(int $id, UpdateHolidayDTO $dto, int $companyId): Holiday;
    public function delete(int $id, int $companyId): bool;
    public function getHolidayByDate(string $date, int $companyId): ?Holiday;
    public function getUpcomingHolidays(int $companyId, int $limit = 10): array;
}
