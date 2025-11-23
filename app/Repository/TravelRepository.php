<?php

namespace App\Repository;

use App\DTOs\Travel\CreateTravelDTO;
use App\DTOs\Travel\UpdateTravelDTO;
use App\Models\Travel;
use App\Repository\Interface\TravelRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class TravelRepository implements TravelRepositoryInterface
{
    public function create(CreateTravelDTO $data): Travel
    {
        return Travel::create($data->toArray());
    }

    public function update(Travel $travel, UpdateTravelDTO $data): Travel
    {
        $travel->update($data->toArray());
        return $travel;
    }

    public function cancel(int $id): bool
    {
        return Travel::destroy($id);
    }

    public function findById(int $id): ?Travel
    {
        return Travel::with(['employee', 'arrangementType:constants_id,category_name'])->find($id);
    }

    public function findByIdAndCompany(int $id, int $companyId): ?Travel
    {
        return Travel::where('travel_id', $id)
            ->where('company_id', $companyId)
            ->with(['employee', 'arrangementType:constants_id,category_name'])
            ->first();
    }

    public function getByCompany(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return Travel::where('company_id', $companyId)
            ->with(['employee:user_id,first_name,last_name', 'arrangementType:constants_id,category_name'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getByEmployee(int $employeeId, int $perPage = 15): LengthAwarePaginator
    {
        return Travel::where('employee_id', $employeeId)
            ->with(['employee:user_id,first_name,last_name', 'arrangementType:constants_id,category_name'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function approve(int $id): Travel
    {
        $travel = Travel::findOrFail($id);
        $travel->status = Travel::STATUS_APPROVED;
        $travel->save();
        $travel->refresh();
        $travel->load(['employee']);

        return $travel;
    }

    public function reject(int $id): Travel
    {
        $travel = Travel::findOrFail($id);
        $travel->status = Travel::STATUS_REJECTED;

        $travel->save();
        $travel->refresh();
        $travel->load(['employee']);

        return $travel;
    }

    public function hasOverlappingTravel(int $employeeId, string $startDate, string $endDate, ?int $excludeTravelId = null): bool
    {
        $query = Travel::where('employee_id', $employeeId)
            ->whereIn('status', [Travel::STATUS_PENDING, Travel::STATUS_APPROVED]) // Only check pending and approved
            ->where(function ($q) use ($startDate, $endDate) {
                // Two date ranges overlap if: (start1 <= end2) AND (end1 >= start2)
                // Use DATE() to compare only date part, ignoring time
                $q->whereRaw('DATE(start_date) <= ?', [$endDate])
                    ->whereRaw('DATE(end_date) >= ?', [$startDate]);
            });

        // Exclude current travel when updating
        if ($excludeTravelId) {
            $query->where('travel_id', '!=', $excludeTravelId);
        }

        $count = $query->count();
        Log::info('TravelRepository::hasOverlappingTravel', [
            'employee_id' => $employeeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'exclude_id' => $excludeTravelId,
            'overlapping_count' => $count,
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        return $count > 0;
    }

    public function search(int $companyId, string $query, int $perPage = 15): LengthAwarePaginator
    {
        return Travel::where('company_id', $companyId)
            ->where(function ($q) use ($query) {
                // Search by visit place
                $q->where('visit_place', 'like', "%{$query}%")
                    // Search by visit purpose
                    ->orWhere('visit_purpose', 'like', "%{$query}%")
                    // Search by employee name
                    ->orWhereHas('employee', function ($employeeQuery) use ($query) {
                        $employeeQuery->where('first_name', 'like', "%{$query}%")
                            ->orWhere('last_name', 'like', "%{$query}%");
                    });
            })
            ->with(['employee:user_id,first_name,last_name', 'arrangementType:constants_id,category_name'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
