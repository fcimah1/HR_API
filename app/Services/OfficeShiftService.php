<?php

namespace App\Services;

use App\DTOs\OfficeShift\OfficeShiftFilterDTO;
use App\DTOs\OfficeShift\CreateOfficeShiftDTO;
use App\DTOs\OfficeShift\UpdateOfficeShiftDTO;
use App\Models\OfficeShift;
use App\Models\User;
use App\Repository\Interface\OfficeShiftRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class OfficeShiftService
{
    public function __construct(
        protected OfficeShiftRepositoryInterface $officeShiftRepository,
        protected SimplePermissionService $permissionService
    ) {}

    /**
     * Get paginated office shifts with permission check
     */
    public function getPaginatedShifts(User $user, OfficeShiftFilterDTO $filters): LengthAwarePaginator
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

        // Ensure filters are for the correct company
        $filters = new OfficeShiftFilterDTO(
            companyId: $effectiveCompanyId,
            search: $filters->search,
            page: $filters->page,
            limit: $filters->limit
        );

        return $this->officeShiftRepository->getPaginatedShifts($filters);
    }

    /**
     * Get shift details
     */
    public function getShiftDetails(User $user, int $shiftId): ?OfficeShift
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        return $this->officeShiftRepository->findShiftInCompany($shiftId, $effectiveCompanyId);
    }

    /**
     * Create a new office shift
     */
    public function createShift(User $user, CreateOfficeShiftDTO $dto): OfficeShift
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

        // Use effective company ID
        $dto = new CreateOfficeShiftDTO(
            companyId: $effectiveCompanyId,
            shiftName: $dto->shiftName,
            mondayInTime: $dto->mondayInTime,
            mondayOutTime: $dto->mondayOutTime,
            tuesdayInTime: $dto->tuesdayInTime,
            tuesdayOutTime: $dto->tuesdayOutTime,
            wednesdayInTime: $dto->wednesdayInTime,
            wednesdayOutTime: $dto->wednesdayOutTime,
            thursdayInTime: $dto->thursdayInTime,
            thursdayOutTime: $dto->thursdayOutTime,
            fridayInTime: $dto->fridayInTime,
            fridayOutTime: $dto->fridayOutTime,
            saturdayInTime: $dto->saturdayInTime,
            saturdayOutTime: $dto->saturdayOutTime,
            sundayInTime: $dto->sundayInTime,
            sundayOutTime: $dto->sundayOutTime,
            mondayLunchBreak: $dto->mondayLunchBreak,
            tuesdayLunchBreak: $dto->tuesdayLunchBreak,
            wednesdayLunchBreak: $dto->wednesdayLunchBreak,
            thursdayLunchBreak: $dto->thursdayLunchBreak,
            fridayLunchBreak: $dto->fridayLunchBreak,
            saturdayLunchBreak: $dto->saturdayLunchBreak,
            sundayLunchBreak: $dto->sundayLunchBreak,
            mondayLunchBreakOut: $dto->mondayLunchBreakOut,
            tuesdayLunchBreakOut: $dto->tuesdayLunchBreakOut,
            wednesdayLunchBreakOut: $dto->wednesdayLunchBreakOut,
            thursdayLunchBreakOut: $dto->thursdayLunchBreakOut,
            fridayLunchBreakOut: $dto->fridayLunchBreakOut,
            saturdayLunchBreakOut: $dto->saturdayLunchBreakOut,
            sundayLunchBreakOut: $dto->sundayLunchBreakOut,
            hoursPerDay: $dto->hoursPerDay,
            inTimeBeginning: $dto->inTimeBeginning,
            inTimeEnd: $dto->inTimeEnd,
            lateAllowance: $dto->lateAllowance,
            outTimeBeginning: $dto->outTimeBeginning,
            outTimeEnd: $dto->outTimeEnd,
            breakStart: $dto->breakStart,
            breakEnd: $dto->breakEnd
        );

        return $this->officeShiftRepository->createShift($dto);
    }

    /**
     * Update an office shift
     */
    public function updateShift(User $user, int $shiftId, UpdateOfficeShiftDTO $dto): bool
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        $shift = $this->officeShiftRepository->findShiftInCompany($shiftId, $effectiveCompanyId);

        if (!$shift) {
            throw new \Exception('نوبة العمل غير موجودة أو ليس لديك صلاحية لتعديلها');
        }

        return $this->officeShiftRepository->updateShift($shift, $dto);
    }

    /**
     * Delete an office shift
     */
    public function deleteShift(User $user, int $shiftId): bool
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        $shift = $this->officeShiftRepository->findShiftInCompany($shiftId, $effectiveCompanyId);

        if (!$shift) {
            throw new \Exception('نوبة العمل غير موجودة أو ليس لديك صلاحية لحذفها');
        }

        // Check if shift is in use
        if ($shift->userDetails()->count() > 0) {
            throw new \Exception('لا يمكن حذف نوبة العمل لأنها مرتبطة بموظفين حاليين');
        }

        return $this->officeShiftRepository->deleteShift($shift);
    }

    /**
     * Get all shifts for a company
     */
    public function getAllShifts(User $user): Collection
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        return $this->officeShiftRepository->getAllShiftsInCompany($effectiveCompanyId);
    }
}
