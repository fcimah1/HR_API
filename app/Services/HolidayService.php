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
        protected HolidayRepositoryInterface $holidayRepository,
        protected CacheService $cacheService,
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

            // مسح الـ cache للعطلات بعد الإضافة
            $this->cacheService->clearHolidaysCache($dto->companyId);

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

            // مسح الـ cache للعطلات بعد التحديث
            $this->cacheService->clearHolidaysCache($companyId);

            return HolidayResponseDTO::fromModel($holiday)->toArray();
        });
    }

    public function deleteHoliday(int $id, int $companyId): bool
    {
        $result = $this->holidayRepository->delete($id, $companyId);

        // مسح الـ cache للعطلات بعد الحذف
        $this->cacheService->clearHolidaysCache($companyId);

        return $result;
    }

    /**
     * Check if date is a holiday (مع Cache)
     */
    public function isHoliday(string $date, int $companyId): bool
    {
        $year = (int)date('Y', strtotime($date));
        $holidays = $this->cacheService->getHolidays($companyId, $year);

        foreach ($holidays as $holiday) {
            $startDate = $holiday->event_start_date ?? ($holiday['event_start_date'] ?? null);
            $endDate = $holiday->event_end_date ?? ($holiday['event_end_date'] ?? null);

            if ($startDate && $endDate && $date >= $startDate && $date <= $endDate) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get holiday info for a date (مع Cache)
     */
    public function getHolidayForDate(string $date, int $companyId): ?array
    {
        $year = (int)date('Y', strtotime($date));
        $holidays = $this->cacheService->getHolidays($companyId, $year);

        foreach ($holidays as $holiday) {
            $startDate = $holiday->event_start_date ?? ($holiday['event_start_date'] ?? null);
            $endDate = $holiday->event_end_date ?? ($holiday['event_end_date'] ?? null);

            if ($startDate && $endDate && $date >= $startDate && $date <= $endDate) {
                return [
                    'event_id' => $holiday->event_id ?? ($holiday['event_id'] ?? null),
                    'event_name' => $holiday->event_name ?? ($holiday['event_name'] ?? null),
                    'event_start_date' => $startDate,
                    'event_end_date' => $endDate,
                ];
            }
        }

        return null;
    }

    /**
     * Get upcoming holidays
     */
    public function getUpcomingHolidays(int $companyId, int $limit = 10): array
    {
        return $this->holidayRepository->getUpcomingHolidays($companyId, $limit);
    }
}
