<?php

namespace App\Services;

use App\Repository\Interface\HolidayRepositoryInterface;
use App\DTOs\Holiday\CreateHolidayDTO;
use App\DTOs\Holiday\UpdateHolidayDTO;
use App\DTOs\Holiday\HolidayResponseDTO;
use Illuminate\Support\Facades\DB;

class HolidayService
{
    public function __construct(
        protected HolidayRepositoryInterface $holidayRepository
    ) {}

    public function getHolidays(int $companyId, array $filters = []): array
    {
        $holidays = $this->holidayRepository->getAll($companyId, $filters);

        return [
            'data' => $holidays->items(),
            'pagination' => [
                'current_page' => $holidays->currentPage(),
                'last_page' => $holidays->lastPage(),
                'per_page' => $holidays->perPage(),
                'total' => $holidays->total(),
                'from' => $holidays->firstItem(),
                'to' => $holidays->lastItem(),
                'has_more_pages' => $holidays->hasMorePages(),
            ]
        ];
    }

    public function getHolidayById(int $id, int $companyId): ?array
    {
        $holiday = $this->holidayRepository->findById($id, $companyId);

        if (!$holiday) {
            return null;
        }

        return HolidayResponseDTO::fromModel($holiday)->toArray();
    }

    public function createHoliday(CreateHolidayDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            // Validate date range
            if ($dto->startDate > $dto->endDate) {
                throw new \Exception('تاريخ البداية يجب أن يكون قبل تاريخ النهاية');
            }

            $holiday = $this->holidayRepository->create($dto);
            return HolidayResponseDTO::fromModel($holiday)->toArray();
        });
    }

    public function updateHoliday(int $id, UpdateHolidayDTO $dto, int $companyId): array
    {
        return DB::transaction(function () use ($id, $dto, $companyId) {
            // Validate date range
            if ($dto->startDate > $dto->endDate) {
                throw new \Exception('تاريخ البداية يجب أن يكون قبل تاريخ النهاية');
            }

            $holiday = $this->holidayRepository->update($id, $dto, $companyId);
            return HolidayResponseDTO::fromModel($holiday)->toArray();
        });
    }

    public function deleteHoliday(int $id, int $companyId): bool
    {
        return $this->holidayRepository->delete($id, $companyId);
    }

    /**
     * Check if date is a holiday
     */
    public function isHoliday(string $date, int $companyId): bool
    {
        $holiday = $this->holidayRepository->getHolidayByDate($date, $companyId);
        return $holiday !== null;
    }

    /**
     * Get holiday info for a date
     */
    public function getHolidayForDate(string $date, int $companyId): ?array
    {
        $holiday = $this->holidayRepository->getHolidayByDate($date, $companyId);

        if (!$holiday) {
            return null;
        }

        return HolidayResponseDTO::fromModel($holiday)->toArray();
    }

    /**
     * Get upcoming holidays
     */
    public function getUpcomingHolidays(int $companyId, int $limit = 10): array
    {
        return $this->holidayRepository->getUpcomingHolidays($companyId, $limit);
    }
}
