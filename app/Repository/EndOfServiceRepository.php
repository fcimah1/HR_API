<?php

declare(strict_types=1);

namespace App\Repository;

use App\Repository\Interface\EndOfServiceRepositoryInterface;
use App\DTOs\EndOfService\EndOfServiceFilterDTO;
use App\DTOs\EndOfService\CreateEndOfServiceDTO;
use App\DTOs\EndOfService\UpdateEndOfServiceDTO;
use App\Models\EndOfService;
use Illuminate\Database\Eloquent\Builder;

class EndOfServiceRepository implements EndOfServiceRepositoryInterface
{
    /**
     * الحصول على جميع الحسابات مع الفلترة والـ Pagination
     */
    public function getAll(EndOfServiceFilterDTO $filters): mixed
    {
        $query = EndOfService::query()
            ->where('company_id', $filters->companyId)
            ->with(['employee:user_id,first_name,last_name', 'calculator:user_id,first_name,last_name']);

        // فلترة بالموظف
        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        // فلترة بنوع انتهاء الخدمة
        if ($filters->terminationType !== null) {
            $query->where('termination_type', $filters->terminationType);
        }

        // فلترة بحالة الموافقة
        if ($filters->isApproved !== null) {
            $query->where('is_approved', $filters->isApproved);
        }

        // فلترة بتاريخ البداية
        if ($filters->fromDate !== null) {
            $query->where('termination_date', '>=', $filters->fromDate);
        }

        // فلترة بتاريخ النهاية
        if ($filters->toDate !== null) {
            $query->where('termination_date', '<=', $filters->toDate);
        }

        // البحث
        if ($filters->search !== null) {
            $query->whereHas('employee', function (Builder $q) use ($filters) {
                $q->where('first_name', 'like', "%{$filters->search}%")
                    ->orWhere('last_name', 'like', "%{$filters->search}%")
                    ->orWhere('employee_id', 'like', "%{$filters->search}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        if ($filters->paginate) {
            return $query->paginate($filters->perPage);
        }

        return $query->get();
    }

    /**
     * الحصول على حساب بالـ ID
     */
    public function getById(int $id, int $companyId): ?EndOfService
    {
        return EndOfService::where('calculation_id', $id)
            ->where('company_id', $companyId)
            ->with(['employee:user_id,first_name,last_name', 'calculator:user_id,first_name,last_name'])
            ->first();
    }

    /**
     * إنشاء حساب جديد
     */
    public function create(CreateEndOfServiceDTO $dto): EndOfService
    {
        return EndOfService::create([
            'company_id' => $dto->companyId,
            'employee_id' => $dto->employeeId,
            'hire_date' => $dto->hireDate,
            'termination_date' => $dto->terminationDate,
            'termination_type' => $dto->terminationType,
            'service_years' => $dto->serviceYears,
            'service_months' => $dto->serviceMonths,
            'service_days' => $dto->serviceDays,
            'basic_salary' => $dto->basicSalary,
            'allowances' => $dto->allowances,
            'total_salary' => $dto->totalSalary,
            'gratuity_amount' => $dto->gratuityAmount,
            'leave_compensation' => $dto->leaveCompensation,
            'notice_compensation' => $dto->noticeCompensation,
            'total_compensation' => $dto->totalCompensation,
            'unused_leave_days' => $dto->unusedLeaveDays,
            'calculated_by' => $dto->calculatedBy,
            'calculated_at' => $dto->calculatedAt,
            'notes' => $dto->notes,
        ]);
    }

    /**
     * تحديث حساب
     */
    public function update(EndOfService $model, UpdateEndOfServiceDTO $dto): EndOfService
    {
        $updateData = [];

        if ($dto->notes !== null) {
            $updateData['notes'] = $dto->notes;
        }

        if ($dto->isApproved !== null) {
            $updateData['is_approved'] = $dto->isApproved;
            if ($dto->isApproved) {
                $updateData['approved_by'] = $dto->approvedBy;
                $updateData['approved_at'] = now();
            }
        }

        if (!empty($updateData)) {
            $model->update($updateData);
        }

        return $model->fresh(['employee', 'calculator']);
    }

    /**
     * حذف حساب
     */
    public function delete(int $id, int $companyId): bool
    {
        return EndOfService::where('calculation_id', $id)
            ->where('company_id', $companyId)
            ->delete() > 0;
    }
    /**
     * البحث عن حساب معلق (غير معتمد) لموظف
     */
    public function findPendingByEmployeeId(int $employeeId, int $companyId): ?EndOfService
    {
        return EndOfService::where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->whereNull('is_approved')->orWhere('is_approved', 0);
            })
            ->latest('created_at')
            ->first();
    }

    /**
     * تحديث حساب ببيانات جديدة (Re-calculate)
     */
    public function updateCalculation(EndOfService $model, CreateEndOfServiceDTO $dto): EndOfService
    {
        $model->update([
            'hire_date' => $dto->hireDate,
            'termination_date' => $dto->terminationDate,
            'termination_type' => $dto->terminationType,
            'service_years' => $dto->serviceYears,
            'service_months' => $dto->serviceMonths,
            'service_days' => $dto->serviceDays,
            'basic_salary' => $dto->basicSalary,
            'allowances' => $dto->allowances,
            'total_salary' => $dto->totalSalary,
            'gratuity_amount' => $dto->gratuityAmount,
            'leave_compensation' => $dto->leaveCompensation,
            'notice_compensation' => $dto->noticeCompensation,
            'total_compensation' => $dto->totalCompensation,
            'unused_leave_days' => $dto->unusedLeaveDays,
            'calculated_by' => $dto->calculatedBy,
            'calculated_at' => $dto->calculatedAt,
            'notes' => $dto->notes,
            // Reset approval status if it was rejected/approved incorrectly (though logic implies only pending)
            'is_approved' => 0,
            'approved_by' => null,
            'approved_at' => null
        ]);

        return $model->fresh(['employee', 'calculator']);
    }
}
