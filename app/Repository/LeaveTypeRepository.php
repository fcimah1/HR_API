<?php

namespace App\Repository;

use App\Repository\Interface\LeaveTypeRepositoryInterface;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\DTOs\Leave\UpdateLeaveTypeDTO;
use App\Models\ErpConstant;
use App\Models\LeaveApplication;
use App\Models\LeaveAdjustment;
use Illuminate\Support\Collection;

class LeaveTypeRepository implements LeaveTypeRepositoryInterface
{
    public function getActiveLeaveTypes(int $companyId, array $filters = []): array
    {
        $query = ErpConstant::query()
            ->where('company_id', $companyId)
            ->where('type', ErpConstant::TYPE_LEAVE_TYPE);
        if (isset($filters['search']) && $filters['search'] !== null && trim($filters['search']) !== '') {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where('category_name', 'like', $searchTerm);
        }
        $perPage = $filters['per_page'] ?? 10;
        $paginator = $query->paginate($perPage, ['*'], 'page', $filters['page'] ?? 1);

        return $paginator->toArray();
    }

    public function findById(int $id): ?object
    {
        return ErpConstant::where('constants_id', $id)
            ->where('type', ErpConstant::TYPE_LEAVE_TYPE)
            ->first();
    }

    public function create(CreateLeaveTypeDTO $dto): object
    {
        // Check if leave type already exists for this company
        $existingLeaveType = ErpConstant::where('company_id', $dto->companyId)
            ->where('type', ErpConstant::TYPE_LEAVE_TYPE)
            ->where('category_name', $dto->name)
            ->first();

        if ($existingLeaveType) {
            throw new \Exception('نوع الإجازة "' . $dto->name . '" موجود بالفعل لهذه الشركة');
        }

        return ErpConstant::create($dto->toArray());
    }

    public function update(UpdateLeaveTypeDTO $dto): object
    {
        // Find the leave type
        $leaveType = ErpConstant::where('constants_id', $dto->leaveTypeId)
            ->where('type', ErpConstant::TYPE_LEAVE_TYPE)
            ->first();

        if (!$leaveType) {
            throw new \Exception('نوع الإجازة غير موجود');
        }

        // Check if another leave type with the same name exists for this company
        $existingLeaveType = ErpConstant::where('company_id', $leaveType->company_id)
            ->where('type', ErpConstant::TYPE_LEAVE_TYPE)
            ->where('category_name', $dto->name)
            ->where('constants_id', '!=', $dto->leaveTypeId)
            ->first();

        if ($existingLeaveType) {
            throw new \Exception('نوع الإجازة "' . $dto->name . '" موجود بالفعل لهذه الشركة');
        }

        $leaveType->update($dto->toArray());
        return $leaveType->fresh();
    }

    public function delete(int $id, int $companyId): bool
    {
        // Find the leave type
        $leaveType = ErpConstant::where('constants_id', $id)
            ->where('company_id', $companyId)
            ->where('type', ErpConstant::TYPE_LEAVE_TYPE)
            ->first();

        if (!$leaveType) {
            throw new \Exception('نوع الإجازة غير موجود أو لا ينتمي لهذه الشركة');
        }

        // Check if the leave type is being used in any leave applications
        $applicationsCount = LeaveApplication::where('leave_type_id', $id)
            ->where('company_id', $companyId)
            ->count();

        if ($applicationsCount > 0) {
            throw new \Exception('لا يمكن إلغاء تفعيل نوع الإجازة حاليا لأنه مستخدم في طلبات إجازة');
        }

        // Check if the leave type is being used in any leave adjustments
        $adjustmentsCount = LeaveAdjustment::where('leave_type_id', $id)
            ->where('company_id', $companyId)
            ->count();

        if ($adjustmentsCount > 0) {
            throw new \Exception('لا يمكن إلغاء تفعيل نوع الإجازة حاليا لأنه مستخدم في تسويات إجازة');
        }

        // Deactivate by setting field_three to 0 (inactive)
        return $leaveType->delete();
    }
}
