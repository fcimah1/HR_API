<?php

namespace App\Repository;

use App\Repository\Interface\HolidayRepositoryInterface;
use App\DTOs\Holiday\CreateHolidayDTO;
use App\DTOs\Holiday\UpdateHolidayDTO;
use App\Models\Holiday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class HolidayRepository implements HolidayRepositoryInterface
{
    public function getAll(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Holiday::byCompany($companyId)->orderBy('start_date', 'desc');

        if (isset($filters['is_publish'])) {
            $query->where('is_publish', $filters['is_publish']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->betweenDates($filters['start_date'], $filters['end_date']);
        }

        if ($filters['search'] !== null && trim($filters['search']) !== '') {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('event_name', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage);
    }

    public function findById(int $id, int $companyId): ?Holiday
    {
        return Holiday::byCompany($companyId)->find($id);
    }

    public function create(CreateHolidayDTO $dto): Holiday
    {
        $holiday = Holiday::create($dto->toArray());

        Log::info('Holiday created', [
            'holiday_id' => $holiday->holiday_id,
            'event_name' => $holiday->event_name,
        ]);

        return $holiday;
    }

    public function update(int $id, UpdateHolidayDTO $dto, int $companyId): Holiday
    {
        $holiday = $this->findById($id, $companyId);

        if (!$holiday) {
            throw new \Exception('العطلة غير موجودة');
        }

        $holiday->update($dto->toArray());

        Log::info('Holiday updated', [
            'holiday_id' => $holiday->holiday_id,
        ]);

        return $holiday->fresh();
    }

    public function delete(int $id, int $companyId): bool
    {
        $holiday = $this->findById($id, $companyId);

        if (!$holiday) {
            return false;
        }

        Log::info('Holiday deleted', [
            'holiday_id' => $id,
        ]);

        return $holiday->delete();
    }

    public function getHolidayByDate(string $date, int $companyId): ?Holiday
    {
        return Holiday::byCompany($companyId)
            ->published()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    public function getUpcomingHolidays(int $companyId, int $limit = 10): array
    {
        return Holiday::byCompany($companyId)
            ->published()
            ->where('start_date', '>=', now()->format('Y-m-d'))
            ->orderBy('start_date', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
