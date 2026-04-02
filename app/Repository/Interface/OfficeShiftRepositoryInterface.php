<?php

namespace App\Repository\Interface;

use App\DTOs\OfficeShift\OfficeShiftFilterDTO;
use App\DTOs\OfficeShift\CreateOfficeShiftDTO;
use App\DTOs\OfficeShift\UpdateOfficeShiftDTO;
use App\Models\OfficeShift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface OfficeShiftRepositoryInterface
{
    /**
     * Get paginated office shifts with filters
     */
    public function getPaginatedShifts(OfficeShiftFilterDTO $filters): LengthAwarePaginator;

    /**
     * Find office shift by ID within company
     */
    public function findShiftInCompany(int $shiftId, int $companyId): ?OfficeShift;

    /**
     * Create office shift
     */
    public function createShift(CreateOfficeShiftDTO $dto): OfficeShift;

    /**
     * Update office shift
     */
    public function updateShift(OfficeShift $shift, UpdateOfficeShiftDTO $dto): bool;

    /**
     * Delete office shift
     */
    public function deleteShift(OfficeShift $shift): bool;

    /**
     * Get all active office shifts for a company
     */
    public function getAllShiftsInCompany(int $companyId): Collection;
}
