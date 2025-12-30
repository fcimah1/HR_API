<?php

namespace App\Repository;

use App\DTOs\Travel\CreateTravelDTO;
use App\DTOs\Travel\TravelRequestFilterDTO;
use App\DTOs\Travel\UpdateTravelDTO;
use App\Models\Travel;
use App\Models\StaffApproval;
use App\Repository\Interface\TravelRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class TravelRepository implements TravelRepositoryInterface
{
    public function create(CreateTravelDTO $data): Travel
    {
        return Travel::create($data->toArray())->load(['employee', 'approvals.staff']);
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
        return Travel::with(['employee', 'arrangementType:constants_id,category_name', 'approvals.staff'])->find($id);
    }

    public function findByIdAndCompany(int $id, int $companyId): ?Travel
    {
        return Travel::where('travel_id', $id)
            ->where('company_id', $companyId)
            ->with(['employee', 'arrangementType:constants_id,category_name', 'approvals.staff'])
            ->first();
    }

    public function getByCompany(int $companyId, TravelRequestFilterDTO $filters): array
    {
        $query = Travel::where('company_id', $companyId);

        // Filter by employee IDs if specified
        if ($filters->employeeIds && !empty($filters->employeeIds)) {
            $query->whereIn('employee_id', $filters->employeeIds);
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        // Handle date range filter
        if ($filters->startDate || $filters->endDate) {
            if ($filters->startDate && $filters->endDate) {
                // Find travels that overlap with the given date range
                $query->where(function ($q) use ($filters) {
                    $q->whereBetween('start_date', [$filters->startDate, $filters->endDate])
                        ->orWhereBetween('end_date', [$filters->startDate, $filters->endDate])
                        ->orWhere(function ($q) use ($filters) {
                            $q->where('start_date', '<=', $filters->startDate)
                                ->where('end_date', '>=', $filters->endDate);
                        });
                });
            } elseif ($filters->startDate) {
                $query->where('end_date', '>=', $filters->startDate);
            } elseif ($filters->endDate) {
                $query->where('start_date', '<=', $filters->endDate);
            }
        }

        if ($filters->travelMode) {
            $query->where('travel_mode', $filters->travelMode);
        }

        if ($filters->arrangementType) {
            $query->where('arrangement_type', $filters->arrangementType);
        }

        if ($filters->search) {
            $searchTerm = "%{$filters->search}%";
            $query->where(function ($q) use ($searchTerm) {
                $q->where('visit_place', 'like', $searchTerm)
                    ->orWhere('visit_purpose', 'like', $searchTerm)
                    ->orWhereHas('employee', function ($employeeQuery) use ($searchTerm) {
                        $employeeQuery->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    });
            });
        }

        // Apply hierarchy level filtering if specified
        if ($filters->hierarchyLevels) {
            $query->whereHas('employee.user_details.designation', function ($q) use ($filters) {
                $q->whereIn('hierarchy_level', $filters->hierarchyLevels);
            });
        }

        $query->orderBy("{$filters->orderBy}", $filters->order);

        $query->with(['employee', 'arrangementType:constants_id,category_name', 'approvals.staff']);

        $paginator = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    public function getByEmployee(int $employeeId, TravelRequestFilterDTO $filters): array
    {
        $query = Travel::where('employee_id', $employeeId);

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        // Handle date range filter
        if ($filters->startDate || $filters->endDate) {
            if ($filters->startDate && $filters->endDate) {
                // Find travels that overlap with the given date range
                $query->where(function ($q) use ($filters) {
                    $q->whereBetween('start_date', [$filters->startDate, $filters->endDate])
                        ->orWhereBetween('end_date', [$filters->startDate, $filters->endDate])
                        ->orWhere(function ($q) use ($filters) {
                            $q->where('start_date', '<=', $filters->startDate)
                                ->where('end_date', '>=', $filters->endDate);
                        });
                });
            } elseif ($filters->startDate) {
                $query->where('end_date', '>=', $filters->startDate);
            } elseif ($filters->endDate) {
                $query->where('start_date', '<=', $filters->endDate);
            }
        }

        if ($filters->travelMode) {
            $query->where('travel_mode', $filters->travelMode);
        }

        if ($filters->arrangementType) {
            $query->where('arrangement_type', $filters->arrangementType);
        }

        if ($filters->search) {
            $searchTerm = "%{$filters->search}%";
            $query->where(function ($q) use ($searchTerm) {
                $q->where('visit_place', 'like', $searchTerm)
                    ->orWhere('visit_purpose', 'like', $searchTerm)
                    ->orWhereHas('employee', function ($employeeQuery) use ($searchTerm) {
                        $employeeQuery->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    });
            });
        }

        // Apply hierarchy level filtering if specified
        if ($filters->hierarchyLevels) {
            $query->whereHas('employee.user_details.designation', function ($q) use ($filters) {
                $q->whereIn('hierarchy_level', $filters->hierarchyLevels);
            });
        }

        $query->orderBy("{$filters->orderBy}", $filters->order);
        $query->with(['employee', 'arrangementType:constants_id,category_name', 'approvals.staff']);
        $paginator = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    public function approve(int $id, int $approvedBy): Travel
    {
        $travel = Travel::findOrFail($id);
        $travel->status = Travel::STATUS_APPROVED;
        $travel->save();

        // إنشاء سجل الموافقة في جدول ci_erp_notifications_approval
        StaffApproval::create([
            'company_id' => $travel->company_id,
            'staff_id' => $approvedBy,
            'module_option' => 'travel_settings',
            'module_key_id' => $travel->travel_id,
            'status' => Travel::STATUS_APPROVED,
            'approval_level' => 1,
            'updated_at' => now(),
        ]);

        $travel->refresh();
        $travel->load(['employee', 'approvals.staff']);

        return $travel;
    }

    public function reject(int $id, int $rejectedBy): Travel
    {
        $travel = Travel::findOrFail($id);
        $travel->status = Travel::STATUS_REJECTED;
        $travel->save();

        // إنشاء سجل الرفض في جدول ci_erp_notifications_approval
        StaffApproval::create([
            'company_id' => $travel->company_id,
            'staff_id' => $rejectedBy,
            'module_option' => 'travel_settings',
            'module_key_id' => $travel->travel_id,
            'status' => Travel::STATUS_REJECTED,
            'approval_level' => 1,
            'updated_at' => now(),
        ]);

        $travel->refresh();
        $travel->load(['employee', 'approvals.staff']);

        return $travel;
    }

    public function hasOverlappingTravel(int $employeeId, string $startDate, string $endDate, ?int $excludeTravelId = null): bool
    {
        // تحويل التواريخ المدخلة إلى كائنات Carbon
        $startDateObj = \Carbon\Carbon::parse($startDate)->startOfDay();
        $endDateObj = \Carbon\Carbon::parse($endDate)->endOfDay();

        // جلب جميع طلبات السفر للموظف (للتصحيح)
        $allTravels = Travel::where('employee_id', $employeeId)
            ->select(['travel_id', 'start_date', 'end_date', 'status'])
            ->get();

        Log::info('All travels for employee', [
            'employee_id' => $employeeId,
            'total_travels' => $allTravels->count(),
            'travels' => $allTravels->toArray()
        ]);

        // جلب طلبات السفر المعلقة والمقبولة فقط
        $existingTravels = Travel::where('employee_id', $employeeId)
            ->whereIn('status', [0, 1, 2]) // 0: Pending (old format), 1: Pending (new format), 2: Approved
            ->when($excludeTravelId, function ($q) use ($excludeTravelId) {
                $q->where('travel_id', '!=', $excludeTravelId);
            })
            ->select(['travel_id', 'start_date', 'end_date', 'status'])
            ->get();

        Log::info('Filtered travels for overlap check', [
            'employee_id' => $employeeId,
            'filtered_count' => $existingTravels->count(),
            'status_filter' => [0, 1, 2], // 0: Pending (old), 1: Pending (new), 2: Approved
            'travels' => $existingTravels->toArray()
        ]);

        // تحقق من التداخل يدوياً
        foreach ($existingTravels as $travel) {
            try {
                $travelStart = \Carbon\Carbon::parse($travel->start_date)->startOfDay();
                $travelEnd = \Carbon\Carbon::parse($travel->end_date)->endOfDay();

                // التحقق من التداخل بين الفترتين
                if ($startDateObj->lte($travelEnd) && $endDateObj->gte($travelStart)) {
                    Log::info('Travel overlap detected', [
                        'employee_id' => $employeeId,
                        'new_start' => $startDateObj->toDateString(),
                        'new_end' => $endDateObj->toDateString(),
                        'existing_id' => $travel->travel_id,
                        'existing_start' => $travelStart->toDateString(),
                        'existing_end' => $travelEnd->toDateString(),
                        'existing_status' => $travel->status
                    ]);
                    return true;
                }
            } catch (\Exception $e) {
                Log::error('Error checking travel overlap', [
                    'travel_id' => $travel->travel_id ?? null,
                    'error' => $e->getMessage(),
                    'start_date' => $travel->start_date ?? null,
                    'end_date' => $travel->end_date ?? null
                ]);
            }
        }

        // تسجيل حالة عدم وجود تداخل
        Log::info('No travel overlap found', [
            'employee_id' => $employeeId,
            'start_date' => $startDateObj->toDateString(),
            'end_date' => $endDateObj->toDateString(),
            'existing_travels_count' => $existingTravels->count()
        ]);

        return false;
    }
}
