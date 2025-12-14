<?php

namespace App\Repository;

use App\DTOs\Complaint\CreateComplaintDTO;
use App\DTOs\Complaint\ComplaintFilterDTO;
use App\DTOs\Complaint\UpdateComplaintDTO;
use App\Models\Complaint;
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
        $query = Complaint::with(['employee']);

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
        return Complaint::with(['employee'])
            ->where('complaint_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * الحصول على شكوى للموظف
     */
    public function findComplaintForEmployee(int $id, int $employeeId): ?Complaint
    {
        return Complaint::with(['employee'])
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
        $complaint->load(['employee']);

        Log::info('Complaint created', [
            'complaint_id' => $complaint->complaint_id,
            'complaint_from' => $complaint->complaint_from,
        ]);

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
        $complaint->load(['employee']);

        Log::info('Complaint updated', [
            'complaint_id' => $complaint->complaint_id,
        ]);

        return $complaint;
    }

    /**
     * حذف شكوى
     */
    public function deleteComplaint(Complaint $complaint): bool
    {
        Log::info('Complaint deleted', [
            'complaint_id' => $complaint->complaint_id,
            'complaint_from' => $complaint->complaint_from,
        ]);

        return $complaint->delete();
    }

    /**
     * حل الشكوى
     */
    public function resolveComplaint(Complaint $complaint, int $resolvedBy, ?string $remarks = null): Complaint
    {
        $complaint->update([
            'status' => Complaint::STATUS_RESOLVED,
        ]);

        $complaint->refresh();
        $complaint->load(['employee']);

        Log::info('Complaint resolved', [
            'complaint_id' => $complaint->complaint_id,
            'resolved_by' => $resolvedBy,
            'remarks' => $remarks,
        ]);

        return $complaint;
    }

    /**
     * رفض الشكوى
     */
    public function rejectComplaint(Complaint $complaint, int $rejectedBy, ?string $remarks = null): Complaint
    {
        $complaint->update([
            'status' => Complaint::STATUS_REJECTED,
        ]);

        $complaint->refresh();
        $complaint->load(['employee']);

        Log::info('Complaint rejected', [
            'complaint_id' => $complaint->complaint_id,
            'rejected_by' => $rejectedBy,
            'remarks' => $remarks,
        ]);

        return $complaint;
    }
}
