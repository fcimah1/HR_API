<?php

namespace App\Repository;

use App\DTOs\Complaint\CreateComplaintDTO;
use App\DTOs\Complaint\ComplaintFilterDTO;
use App\DTOs\Complaint\UpdateComplaintDTO;
use App\Enums\NumericalStatusEnum;
use App\Enums\StringStatusEnum;
use App\Models\Complaint;
use App\Models\StaffApproval;
use App\Models\User;
use App\Repository\Interface\ComplaintRepositoryInterface;
use Illuminate\Support\Facades\Log;

class ComplaintRepository implements ComplaintRepositoryInterface
{
    /**
     * الحصول على قائمة الشكاوى مع التصفية والترقيم الصفحي
     */
    public function getPaginatedComplaints(ComplaintFilterDTO $filters, User $user): array
    {
        $query = Complaint::with(['employee', 'approvals.staff']);

        // تطبيق فلتر الشركة
        if ($filters->companyId !== null) {
            $query->where('company_id', $filters->companyId);
        }

        // تطبيق فلتر الموظف المحدد
        if ($filters->employeeId !== null) {
            $query->where('complaint_from', $filters->employeeId);
        }

        // تطبيق فلتر قائمة الموظفين (للمديرين)
        if ($filters->employeeIds !== null && !empty($filters->employeeIds)) {
            $query->whereIn('complaint_from', $filters->employeeIds);
        }

        // تطبيق فلتر الحالة
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        // تطبيق البحث
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm)
                    ->orWhere('complaint_against', 'like', $searchTerm)
                    ->orWhereHas('employee', function ($subQuery) use ($searchTerm) {
                        $subQuery->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    });
            });
        }

        // تطبيق فلتر التاريخ
        if ($filters->fromDate !== null && $filters->toDate !== null) {
            $query->whereBetween('complaint_date', [$filters->fromDate, $filters->toDate]);
        } elseif ($filters->fromDate !== null) {
            $query->where('complaint_date', '>=', $filters->fromDate);
        } elseif ($filters->toDate !== null) {
            $query->where('complaint_date', '<=', $filters->toDate);
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
     * الحصول على شكوى بواسطة المعرف
     */
    public function findComplaintById(int $id, int $companyId): ?Complaint
    {
        return Complaint::with(['employee', 'approvals.staff'])
            ->where('complaint_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * الحصول على شكوى للموظف
     */
    public function findComplaintForEmployee(int $id, int $employeeId): ?Complaint
    {
        return Complaint::with(['employee', 'approvals.staff'])
            ->where('complaint_id', $id)
            ->where('complaint_from', $employeeId)
            ->first();
    }

    /**
     * إنشاء شكوى جديدة
     */
    public function createComplaint(CreateComplaintDTO $dto): Complaint
    {
        $complaint = Complaint::create($dto->toArray());
        $complaint->load(['employee', 'approvals.staff']);

        return $complaint;
    }

    /**
     * تحديث شكوى
     */
    public function updateComplaint(Complaint $complaint, UpdateComplaintDTO $dto): Complaint
    {
        $updateData = $dto->toArray();

        if (!empty($updateData)) {
            $complaint->update($updateData);
        }

        $complaint->refresh();
        $complaint->load(['employee', 'approvals.staff']);

        return $complaint;
    }

    /**
     * حذف شكوى
     */
    public function deleteComplaint(Complaint $complaint, int $rejectedBy, ?string $description = null): bool
    {
        $complaint->update([
            'status' => NumericalStatusEnum::REJECTED->value,
            'description' => "تم إلغاء الشكوى بواسطة " . User::getFullNameById($rejectedBy) . " بتاريخ " . now()->format('Y-m-d H:i:s') . ($description ? " | السبب: " . $description : ""),
        ]);

        // إنشاء سجل الرفض في جدول ci_erp_notifications_approval
        StaffApproval::create([
            'company_id' => $complaint->company_id,
            'staff_id' => $rejectedBy,
            'module_option' => 'complaint_settings',
            'module_key_id' => $complaint->complaint_id,
            'status' => NumericalStatusEnum::REJECTED->value,
            'approval_level' => 1,
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * حل الشكوى
     */
    public function resolveComplaint(Complaint $complaint, int $resolvedBy, ?string $description = null): Complaint
    {
        $complaint->update([
            'status' => NumericalStatusEnum::APPROVED->value,
            'description' => $description . "__" . User::getFullNameById($resolvedBy) . " تم حل الشكوى بواسطة " . now()->format('Y-m-d H:i:s'),
        ]);

        // إنشاء سجل الحل/الموافقة في جدول ci_erp_notifications_approval
        StaffApproval::create([
            'company_id' => $complaint->company_id,
            'staff_id' => $resolvedBy,
            'module_option' => 'complaint_settings',
            'module_key_id' => $complaint->complaint_id,
            'status' => NumericalStatusEnum::APPROVED->value,
            'approval_level' => 1,
            'updated_at' => now(),
        ]);

        $complaint->refresh();
        $complaint->load(['employee', 'approvals.staff']);

        return $complaint;
    }

    /**
     * رفض الشكوى
     */
    public function rejectComplaint(Complaint $complaint, int $rejectedBy, ?string $description = null): Complaint
    {
        $complaint->update([
            'status' => NumericalStatusEnum::REJECTED->value,
            'description' => $description . "__" . User::getFullNameById($rejectedBy) . " تم رفض الشكوى بواسطة " . now()->format('Y-m-d H:i:s'),
        ]);

        // إنشاء سجل الرفض في جدول ci_erp_notifications_approval
        StaffApproval::create([
            'company_id' => $complaint->company_id,
            'staff_id' => $rejectedBy,
            'module_option' => 'complaint_settings',
            'module_key_id' => $complaint->complaint_id,
            'status' => NumericalStatusEnum::REJECTED->value,
            'approval_level' => 1,
            'updated_at' => now(),
        ]);

        $complaint->refresh();
        $complaint->load(['employee', 'approvals.staff']);

        Log::info('Complaint rejected', [
            'complaint_id' => $complaint->complaint_id,
            'rejected_by' => $rejectedBy,
            'description' => $description,
        ]);

        return $complaint;
    }
}
