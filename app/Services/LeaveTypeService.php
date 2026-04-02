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
    protected $cacheService;

    public function __construct(
        LeaveTypeRepositoryInterface $leaveTypeRepository,
        SimplePermissionService $permissionService,
        CacheService $cacheService
    ) {
        $this->leaveTypeRepository = $leaveTypeRepository;
        $this->permissionService = $permissionService;
        $this->cacheService = $cacheService;
    }

    public function getActiveLeaveTypes(int $companyId, array $filters, ?User $user = null): array
    {

        $result = $this->leaveTypeRepository->getActiveLeaveTypes($companyId, $filters);

        // الحصول على أنواع الإجازات المحظورة للموظف
        $restrictedLeaveTypeIds = [];
        if ($user && $user->user_type !== 'company') {
            $restriction = \App\Models\OperationRestriction::where('user_id', $user->user_id)
                ->where('company_id', $companyId)
                ->first();

            if ($restriction) {
                $restrictedOperations = $restriction->restricted_operations;
                // استخراج IDs من leave_type_{id}
                foreach ($restrictedOperations as $operation) {
                    if (preg_match('/^leave_type_(\d+)$/', $operation, $matches)) {
                        $restrictedLeaveTypeIds[] = (int) $matches[1];
                    }
                }
            }
        }

        // تحويل البيانات إلى الصيغة المطلوبة وفلترة المحظور
        $filteredData = array_values(array_filter(array_map(function ($constant) use ($restrictedLeaveTypeIds) {
            $leaveTypeId = $constant['constants_id'];

            // تجاوز أنواع الإجازات المحظورة
            if (in_array($leaveTypeId, $restrictedLeaveTypeIds)) {
                return null;
            }

            $leaveData = unserialize($constant['field_one']);
            $quotaAssign = $leaveData['quota_assign'] ?? [];

            // تحويل جميع القيم إلى أرقام
            $quotaAssign = array_map('intval', $quotaAssign);

            // تجميع البيانات حسب السنة
            $yearlyBreakdown_days    = [];
            foreach ($quotaAssign as $yearIndex => $days) {
                if ($days > 0) {
                    $yearlyBreakdown_days[] = "السنة " . ($yearIndex + 1) . ": " . ($days) . " يوم";
                }
            }

            return [
                'leave_type_id' => $constant['constants_id'],
                'leave_type_name' => $constant['category_name'],
                'leave_type_short_name' => $constant['field_one'] ?? '',
                'yearly_breakdown_days' => $yearlyBreakdown_days,
            ];
        }, $result['data']), fn($item) => $item !== null));

        // تحديث الـ pagination بعد الفلترة
        $result['data'] = $filteredData;
        $filteredCount = count($filteredData);
        $result['total'] = $filteredCount;
        $result['to'] = $filteredCount > 0 ? $filteredCount : null;
        $result['last_page'] = max(1, ceil($filteredCount / ($result['per_page'] ?? 15)));

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
        foreach ($quotaAssign as $yearIndex => $days) {
            if ($days > 0) {
                $yearlyBreakdown_days[] = "السنة " . ($yearIndex + 1) . ": " . ($days) . " يوم";
            }
        }
        return [
            'leave_type_id' => $leaveType->constants_id,
            'leave_type_name' => $leaveType->category_name,
            'leave_type_short_name' => $leaveType->field_one ?? '',
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

            // مسح الـ cache لأنواع الإجازات بعد الإضافة
            $this->cacheService->clearLeaveTypesCache($dto->companyId);

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
                'leave_hours' => $totalHours . ' hours',
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

            // مسح الـ cache لأنواع الإجازات بعد التحديث
            $this->cacheService->clearLeaveTypesCache($leaveType->company_id);

            Log::info('LeaveTypeService::updateLeaveType - Transaction committed', [
                'leave_type_id' => $leaveType->constants_id
            ]);
            $leaveData = unserialize($leaveType->field_one);
            $quotaAssign = $leaveData['quota_assign'] ?? [];
            // تجميع البيانات حسب السنة
            $yearlyBreakdown_days    = [];
            foreach ($quotaAssign as $yearIndex => $days) {
                if ($days > 0) {
                    $yearlyBreakdown_days[] = "السنة " . ($yearIndex + 1) . ": " . ($days) . " يوم";
                }
            }
            return [
                'leave_type_id' => $leaveType->constants_id,
                'leave_type_name' => $leaveType->category_name,
                'leave_type_short_name' => $leaveType->field_one ?? '',
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

            // مسح الـ cache لأنواع الإجازات بعد الحذف
            $this->cacheService->clearLeaveTypesCache($effectiveCompanyId);

            Log::info('LeaveTypeService::deleteLeaveType - Transaction committed', ['id' => $id]);
            return $result;
        });
    }
}
