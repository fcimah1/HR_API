<?php

namespace App\Repository;

use App\DTOs\OfficeShift\OfficeShiftFilterDTO;
use App\DTOs\OfficeShift\CreateOfficeShiftDTO;
use App\DTOs\OfficeShift\UpdateOfficeShiftDTO;
use App\Models\OfficeShift;
use App\Repository\Interface\OfficeShiftRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class OfficeShiftRepository implements OfficeShiftRepositoryInterface
{
    /**
     * Get paginated office shifts with filters
     */
    public function getPaginatedShifts(OfficeShiftFilterDTO $filters): LengthAwarePaginator
    {
        $query = OfficeShift::where('company_id', $filters->companyId);

        if ($filters->search) {
            $query->where('shift_name', 'like', '%' . $filters->search . '%');
        }

        return $query->paginate($filters->limit, ['*'], 'page', $filters->page);
    }

    /**
     * Find office shift by ID within company
     */
    public function findShiftInCompany(int $shiftId, int $companyId): ?OfficeShift
    {
        return OfficeShift::where('office_shift_id', $shiftId)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Create office shift
     */
    public function createShift(CreateOfficeShiftDTO $dto): OfficeShift
    {
        return OfficeShift::create($dto->toArray());
    }

    /**
     * Update office shift
     */
    public function updateShift(OfficeShift $shift, UpdateOfficeShiftDTO $dto): bool
    {
        return $shift->update($dto->toArray());
    }

    /**
     * Delete office shift
     */
    public function deleteShift(OfficeShift $shift): bool
    {
        return $shift->delete();
    }

    /**
     * Get all active office shifts for a company
     */
    public function getAllShiftsInCompany(int $companyId): Collection
    {
        return OfficeShift::where('company_id', $companyId)->get();
    }
}
