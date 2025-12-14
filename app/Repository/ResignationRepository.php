<?php

namespace App\Repository;

use App\DTOs\Resignation\CreateResignationDTO;
use App\DTOs\Resignation\ResignationFilterDTO;
use App\DTOs\Resignation\UpdateResignationDTO;
use App\Models\Resignation;
use App\Models\User;
use App\Repository\Interface\ResignationRepositoryInterface;
use Illuminate\Support\Facades\Log;

class ResignationRepository implements ResignationRepositoryInterface
{
    /**
     * الحصول على قائمة الاستقالات مع التصفية والترقيم الصفحي
     */
    public function getPaginatedResignations(ResignationFilterDTO $filters, User $user): array
    {
        $query = Resignation::with(['employee', 'addedBy']);

        // تطبيق فلتر الشركة
        if ($filters->companyId !== null) {
            $query->where('company_id', $filters->companyId);
        }

        // تطبيق فلتر الموظف المحدد
        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        // تطبيق فلتر قائمة الموظفين (للمديرين)
        if ($filters->employeeIds !== null && !empty($filters->employeeIds)) {
            $query->whereIn('employee_id', $filters->employeeIds);
        }

        // تطبيق فلتر الحالة
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        // تطبيق البحث
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('reason', 'like', $searchTerm)
                    ->orWhereHas('employee', function ($subQuery) use ($searchTerm) {
                        $subQuery->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    });
            });
        }

        // تطبيق فلتر التاريخ
        if ($filters->fromDate !== null && $filters->toDate !== null) {
            $query->whereBetween('resignation_date', [$filters->fromDate, $filters->toDate]);
        } elseif ($filters->fromDate !== null) {
            $query->where('resignation_date', '>=', $filters->fromDate);
        } elseif ($filters->toDate !== null) {
            $query->where('resignation_date', '<=', $filters->toDate);
        }

        // ترتيب حسب التاريخ تنازليًا
        $query->orderBy('created_at', 'desc');

        // استخدام paginate
        $paginator = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * الحصول على استقالة بواسطة المعرف
     */
    public function findResignationById(int $id, int $companyId): ?Resignation
    {
        return Resignation::with(['employee', 'addedBy'])
            ->where('resignation_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * الحصول على استقالة للموظف
     */
    public function findResignationForEmployee(int $id, int $employeeId): ?Resignation
    {
        return Resignation::with(['employee', 'addedBy'])
            ->where('resignation_id', $id)
            ->where('employee_id', $employeeId)
            ->first();
    }

    /**
     * إنشاء استقالة جديدة
     */
    public function createResignation(CreateResignationDTO $dto): Resignation
    {
        $resignation = Resignation::create($dto->toArray());
        $resignation->load(['employee', 'addedBy']);

        Log::info('Resignation created', [
            'resignation_id' => $resignation->resignation_id,
            'employee_id' => $resignation->employee_id,
        ]);

        return $resignation;
    }

    /**
     * تحديث استقالة
     */
    public function updateResignation(Resignation $resignation, UpdateResignationDTO $dto): Resignation
    {
        $updateData = $dto->toArray();

        if (!empty($updateData)) {
            $resignation->update($updateData);
        }

        $resignation->refresh();
        $resignation->load(['employee', 'addedBy']);

        Log::info('Resignation updated', [
            'resignation_id' => $resignation->resignation_id,
        ]);

        return $resignation;
    }

    /**
     * حذف استقالة
     */
    public function deleteResignation(Resignation $resignation): bool
    {
        Log::info('Resignation deleted', [
            'resignation_id' => $resignation->resignation_id,
            'employee_id' => $resignation->employee_id,
        ]);

        return $resignation->delete();
    }

    /**
     * الموافقة على استقالة
     */
    public function approveResignation(Resignation $resignation, int $approvedBy, ?string $remarks = null): Resignation
    {
        $resignation->update([
            'status' => Resignation::STATUS_APPROVED,
            'signed_date' => now()->format('Y-m-d'),
            'is_signed' => 1,
        ]);

        $resignation->refresh();
        $resignation->load(['employee', 'addedBy']);

        Log::info('Resignation approved', [
            'resignation_id' => $resignation->resignation_id,
            'approved_by' => $approvedBy,
            'remarks' => $remarks,
        ]);

        return $resignation;
    }

    /**
     * رفض استقالة
     */
    public function rejectResignation(Resignation $resignation, int $rejectedBy, ?string $remarks = null): Resignation
    {
        $resignation->update([
            'status' => Resignation::STATUS_REJECTED,
        ]);

        $resignation->refresh();
        $resignation->load(['employee', 'addedBy']);

        Log::info('Resignation rejected', [
            'resignation_id' => $resignation->resignation_id,
            'rejected_by' => $rejectedBy,
            'remarks' => $remarks,
        ]);

        return $resignation;
    }
}
