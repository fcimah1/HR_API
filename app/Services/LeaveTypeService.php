<?php

namespace App\Services;

use App\Repository\Interface\LeaveTypeRepositoryInterface;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\DTOs\Leave\UpdateLeaveTypeDTO;
use App\DTOs\LeaveType\LeaveTypeFilterDTO;
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

public function getActiveLeaveTypes(int $companyId, array $filters): array
{

    $result = $this->leaveTypeRepository->getActiveLeaveTypes($companyId, $filters);
    
    // تحويل البيانات إلى الصيغة المطلوبة
    $result['data'] = array_map(function ($constant) {
        $leaveData = unserialize($constant['field_one']);
        $quotaAssign = $leaveData['quota_assign'] ?? [];
        
        // تحويل جميع القيم إلى أرقام
        $quotaAssign = array_map('intval', $quotaAssign);
                
        // تجميع البيانات حسب السنة
        $yearlyBreakdown_days    = [];
        $yearlyBreakdown_hours = [];
        foreach ($quotaAssign as $yearIndex => $hours) {
            if ($hours > 0) {
                $yearlyBreakdown_days[] = "السنة " . ($yearIndex + 1) . ": " . ($hours / 8) . " يوم";
                $yearlyBreakdown_hours[] = "السنة " . ($yearIndex + 1) . ": " . $hours . " ساعة";
            }
        }
        
        return [
            'leave_type_id' => $constant['constants_id'],
            'leave_type_name' => $constant['category_name'],
            'leave_type_short_name' => $constant['field_one'] ?? '',
            'yearly_breakdown_days' => $yearlyBreakdown_days,
            'yearly_breakdown_hours' => $yearlyBreakdown_hours,

        ];
    }, $result['data']);
    
    return $result;
}

    public function getLeaveType(int $id): array
    {
        $leaveType = $this->leaveTypeRepository->findById($id);

        if (!$leaveType) {
            throw new \Exception('نوع الإجازة غير موجود');
        }
    $leaveData = unserialize($leaveType['field_one']);
            $quotaAssign = $leaveData['quota_assign'] ?? [];

        // تحويل جميع القيم إلى أرقام
        $quotaAssign = array_map('intval', $quotaAssign);
                
        // تجميع البيانات حسب السنة
        $yearlyBreakdown_days    = [];
        $yearlyBreakdown_hours = [];
        foreach ($quotaAssign as $yearIndex => $hours) {
            if ($hours > 0) {
                $yearlyBreakdown_days[] = "السنة " . ($yearIndex + 1) . ": " . ($hours / 8) . " يوم";
                $yearlyBreakdown_hours[] = "السنة " . ($yearIndex + 1) . ": " . $hours . " ساعة";
            }
        }
        return [
            'leave_type_id' => $leaveType->constants_id,
            'leave_type_name' => $leaveType->category_name,
            'leave_type_short_name' => $leaveType->field_one ?? '',
            'leave_hours' => $yearlyBreakdown_hours,
            'leave_days' => $yearlyBreakdown_days,
            'company_id' => $leaveType->company_id,
        ];
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
            $leaveData = unserialize($leaveType->field_one);
            $quotaAssign = $leaveData['quota_assign'] ?? [];
            $totalHours = array_sum(array_map('intval', $quotaAssign)); 
            return [
                'leave_type_id' => $leaveType->constants_id,
                'leave_type_name' => $leaveType->leave_type_name,
                'leave_type_short_name' => $leaveType->leave_type_short_name,
                'leave_hours' => $totalHours. ' hours',
                'leave_days' => $totalHours / 8 . " days",
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
        $leaveData = unserialize($leaveType->field_one);
        $quotaAssign = $leaveData['quota_assign'] ?? [];
       // تجميع البيانات حسب السنة
        $yearlyBreakdown_days    = [];
        $yearlyBreakdown_hours = [];
        foreach ($quotaAssign as $yearIndex => $hours) {
            if ($hours > 0) {
                $yearlyBreakdown_days[] = "السنة " . ($yearIndex + 1) . ": " . ($hours / 8) . " يوم";
                $yearlyBreakdown_hours[] = "السنة " . ($yearIndex + 1) . ": " . $hours . " ساعة";
            }
        }            return [
                'leave_type_id' => $leaveType->constants_id,
                'leave_type_name' => $leaveType->category_name,
                'leave_type_short_name' => $leaveType->field_one ?? '',
                'leave_hours' => $yearlyBreakdown_hours,
                'leave_days' => $yearlyBreakdown_days,                
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
