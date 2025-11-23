<?php

namespace App\Services;

use App\Repository\Interface\LeaveTypeRepositoryInterface;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\DTOs\Leave\UpdateLeaveTypeDTO;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveTypeService
{
    protected $leaveTypeRepository;
    protected $permissionService;

    public function __construct(
        LeaveTypeRepositoryInterface $leaveTypeRepository,
        SimplePermissionService $permissionService
    ) {
        $this->leaveTypeRepository = $leaveTypeRepository;
        $this->permissionService = $permissionService;
    }

    public function getActiveLeaveTypes(int $companyId): array
    {
        $leaveTypes = $this->leaveTypeRepository->getActiveLeaveTypes($companyId);

        return $leaveTypes->map(function ($constant) {
            return [
                'leave_type_id' => $constant->constants_id,
                'leave_type_name' => $constant->leave_type_name,
                'leave_type_short_name' => $constant->leave_type_short_name,
                'leave_days' => $constant->leave_days,
                'leave_type_status' => $constant->leave_type_status,
                'company_id' => $constant->company_id,
            ];
        })->toArray();
    }

    public function getLeaveType(int $id): object
    {
        $leaveType = $this->leaveTypeRepository->findById($id);

        if (!$leaveType) {
            throw new \Exception('نوع الإجازة غير موجود');
        }

        return $leaveType;
    }

    public function createLeaveType(CreateLeaveTypeDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            Log::info('LeaveTypeService::createLeaveType - Transaction started', [
                'company_id' => $dto->companyId,
                'leave_type_name' => $dto->name
            ]);

            $leaveType = $this->leaveTypeRepository->create($dto);

            Log::info('LeaveTypeService::createLeaveType - Transaction committed', [
                'leave_type_id' => $leaveType->constants_id
            ]);

            return [
                'leave_type_id' => $leaveType->constants_id,
                'leave_type_name' => $leaveType->leave_type_name,
                'leave_type_short_name' => $leaveType->leave_type_short_name,
                'leave_days' => $leaveType->leave_days,
                'leave_type_status' => $leaveType->leave_type_status,
                'company_id' => $leaveType->company_id,
            ];
        });
    }

    public function updateLeaveType(UpdateLeaveTypeDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            Log::info('LeaveTypeService::updateLeaveType - Transaction started', [
                'leave_type_id' => $dto->leaveTypeId,
                'leave_type_name' => $dto->name
            ]);

            $leaveType = $this->leaveTypeRepository->update($dto);

            Log::info('LeaveTypeService::updateLeaveType - Transaction committed', [
                'leave_type_id' => $leaveType->constants_id
            ]);

            return [
                'leave_type_id' => $leaveType->constants_id,
                'leave_type_name' => $leaveType->leave_type_name,
                'leave_type_short_name' => $leaveType->leave_type_short_name,
                'leave_days' => $leaveType->leave_days,
                'leave_type_status' => $leaveType->leave_type_status,
                'company_id' => $leaveType->company_id,
            ];
        });
    }

    public function deleteLeaveType(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            Log::info('LeaveTypeService::deleteLeaveType - Transaction started', ['id' => $id]);

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $result = $this->leaveTypeRepository->delete($id, $effectiveCompanyId);

            Log::info('LeaveTypeService::deleteLeaveType - Transaction committed', ['id' => $id]);
            return $result;
        });
    }
}
