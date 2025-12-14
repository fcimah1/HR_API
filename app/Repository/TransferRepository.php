<?php

namespace App\Repository;

use App\DTOs\Transfer\CreateTransferDTO;
use App\DTOs\Transfer\TransferFilterDTO;
use App\DTOs\Transfer\UpdateTransferDTO;
use App\Models\Transfer;
use App\Models\User;
use App\Repository\Interface\TransferRepositoryInterface;
use Illuminate\Support\Facades\Log;

class TransferRepository implements TransferRepositoryInterface
{
    /**
     * الحصول على قائمة النقل مع التصفية والترقيم الصفحي
     */
    public function getPaginatedTransfers(TransferFilterDTO $filters, User $user): array
    {
        $query = Transfer::with(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation']);

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

        // تطبيق فلتر القسم
        if ($filters->departmentId !== null) {
            $query->where(function ($q) use ($filters) {
                $q->where('old_department', $filters->departmentId)
                    ->orWhere('transfer_department', $filters->departmentId);
            });
        }

        // تطبيق فلتر نوع النقل
        if ($filters->transferType !== null) {
            $query->where('transfer_type', $filters->transferType);
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
            $query->whereBetween('transfer_date', [$filters->fromDate, $filters->toDate]);
        } elseif ($filters->fromDate !== null) {
            $query->where('transfer_date', '>=', $filters->fromDate);
        } elseif ($filters->toDate !== null) {
            $query->where('transfer_date', '<=', $filters->toDate);
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
     * الحصول على نقل بواسطة المعرف
     */
    public function findTransferById(int $id, int $companyId): ?Transfer
    {
        return Transfer::with(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation'])
            ->where('transfer_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * الحصول على نقل للموظف
     */
    public function findTransferForEmployee(int $id, int $employeeId): ?Transfer
    {
        return Transfer::with(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation'])
            ->where('transfer_id', $id)
            ->where('employee_id', $employeeId)
            ->first();
    }

    /**
     * إنشاء نقل جديد
     */
    public function createTransfer(CreateTransferDTO $dto): Transfer
    {
        $transfer = Transfer::create($dto->toArray());
        $transfer->load(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation']);

        Log::info('Transfer created', [
            'transfer_id' => $transfer->transfer_id,
            'employee_id' => $transfer->employee_id,
        ]);

        return $transfer;
    }

    /**
     * تحديث نقل
     */
    public function updateTransfer(Transfer $transfer, UpdateTransferDTO $dto): Transfer
    {
        $updateData = $dto->toArray();

        if (!empty($updateData)) {
            $transfer->update($updateData);
        }

        $transfer->refresh();
        $transfer->load(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation']);

        Log::info('Transfer updated', [
            'transfer_id' => $transfer->transfer_id,
        ]);

        return $transfer;
    }

    /**
     * حذف نقل
     */
    public function deleteTransfer(Transfer $transfer): bool
    {
        Log::info('Transfer deleted', [
            'transfer_id' => $transfer->transfer_id,
            'employee_id' => $transfer->employee_id,
        ]);

        return $transfer->delete();
    }

    /**
     * الموافقة على نقل
     */
    public function approveTransfer(Transfer $transfer, int $approvedBy, ?string $remarks = null): Transfer
    {
        $transfer->update([
            'status' => Transfer::STATUS_APPROVED,
        ]);

        $transfer->refresh();
        $transfer->load(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation']);

        Log::info('Transfer approved', [
            'transfer_id' => $transfer->transfer_id,
            'approved_by' => $approvedBy,
            'remarks' => $remarks,
        ]);

        return $transfer;
    }

    /**
     * رفض نقل
     */
    public function rejectTransfer(Transfer $transfer, int $rejectedBy, ?string $remarks = null): Transfer
    {
        $transfer->update([
            'status' => Transfer::STATUS_REJECTED,
        ]);

        $transfer->refresh();
        $transfer->load(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation']);

        Log::info('Transfer rejected', [
            'transfer_id' => $transfer->transfer_id,
            'rejected_by' => $rejectedBy,
            'remarks' => $remarks,
        ]);

        return $transfer;
    }
}
