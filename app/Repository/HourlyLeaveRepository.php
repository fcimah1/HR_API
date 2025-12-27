<?php

namespace App\Repository;

use App\DTOs\Leave\CreateHourlyLeaveDTO;
use App\DTOs\Leave\HourlyLeaveFilterDTO;
use App\DTOs\Leave\UpdateHourlyLeaveDTO;
use App\Models\LeaveApplication;
use App\Models\StaffApproval;
use App\Models\User;
use App\Repository\Interface\HourlyLeaveRepositoryInterface;
use Illuminate\Support\Facades\Log;

class HourlyLeaveRepository implements HourlyLeaveRepositoryInterface
{
    /**
     * الحصول على قائمة طلبات الإستئذان بالساعات مع التصفية والترقيم الصفحي
     */
    public function getPaginatedHourlyLeaves(HourlyLeaveFilterDTO $filters, User $user): array
    {
        $companyId = $filters->companyId;

        // فلتر طلبات الإجازة (leave_hours > 0 & leave_hours < 8)
        $query = LeaveApplication::where('company_id', $companyId)
            ->where('leave_hours', '>', 0) // ساعات أكبر من صفر
            ->where('leave_hours', '<', 8) // ساعات أقل من 8
            ->with(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);

        // تطبيق فلتر البحث
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                // البحث في بيانات الموظف
                $q->whereHas('employee', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });

                // البحث في بيانات موظف المناوبة
                $q->orWhereHas('dutyEmployee', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm);
                });

                // البحث في نوع الإجازة
                $q->orWhereHas('leaveType', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('category_name', 'like', $searchTerm);
                });

                // البحث في حقول الإجازة نفسها
                $q->orWhere('reason', 'like', $searchTerm);
                $q->orWhere('from_date', 'like', $searchTerm);
                $q->orWhere('status', 'like', $searchTerm);
            });
        }

        // تطبيق الفلاتر الأخرى
        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        // فلتر معرفات الموظفين (للتبعية)
        if ($filters->employeeIds !== null && is_array($filters->employeeIds) && !empty($filters->employeeIds)) {
            $query->whereIn('employee_id', $filters->employeeIds);
        }

        // فلتر الحالة
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        // فلتر نوع الإجازة
        if ($filters->leaveTypeId !== null) {
            $query->where('leave_type_id', $filters->leaveTypeId);
        }

        // فلتر تاريخ البداية
        if ($filters->fromDate !== null) {
            $query->where('from_date', '>=', $filters->fromDate);
        }

        // فلتر تاريخ النهاية
        if ($filters->toDate !== null) {
            $query->where('from_date', '<=', $filters->toDate);
        }

        // تطبيق الفرز
        $sortBy = in_array($filters->sortBy, ['created_at', 'from_date', 'status'])
            ? $filters->sortBy
            : 'created_at';

        $sortDirection = strtolower($filters->sortDirection) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $paginator = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * الحصول على طلب إستئذان بالساعات بواسطة المعرف
     */
    public function findHourlyLeaveById(int $id, int $companyId): ?LeaveApplication
    {
        return LeaveApplication::where('company_id', $companyId)
            ->where('leave_hours', '>', 0) // ساعات أكبر من صفر
            ->where('leave_hours', '<', 8) // ساعات أقل من 8
            ->with(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff'])
            ->find($id);
    }

    /**
     * الحصول على طلب إستئذان بالساعات للموظف
     */
    public function findHourlyLeaveForEmployee(int $id, int $employeeId): ?LeaveApplication
    {
        return LeaveApplication::where('employee_id', $employeeId)
            ->where('leave_hours', '>', 0)
            ->where('leave_hours', '<', 8)
            ->with(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff'])
            ->find($id);
    }

    /**
     * إنشاء طلب إستئذان بالساعات
     */
    public function createHourlyLeave(CreateHourlyLeaveDTO $dto): LeaveApplication
    {
        $application = LeaveApplication::create($dto->toArray());
        $application->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);

        return $application;
    }

    /**
     * إلغاء طلب إستئذان بالساعات
     */
    public function cancelHourlyLeave(LeaveApplication $application, int $cancelledBy, string $reason): LeaveApplication
    {
        $application->update([
            'status' => LeaveApplication::STATUS_REJECTED,
            'remarks' => $reason,
        ]);
        StaffApproval::create([
            'company_id' => $application->company_id,
            'staff_id' => $cancelledBy,
            'module_option' => 'leave_settings',
            'module_key_id' => $application->leave_id,
            'status' => LeaveApplication::STATUS_REJECTED,
            'approval_level' => 1,
            'updated_at' => now(),
        ]);

        $application->refresh();
        $application->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);

        return $application;
    }

    /**
     * الموافقة على طلب إستئذان بالساعات
     */
    public function approveHourlyLeave(LeaveApplication $application, int $approvedBy, ?string $remarks = null): LeaveApplication
    {
        $application->update([
            'status' => LeaveApplication::STATUS_APPROVED,
            'remarks' => $remarks,
        ]);

        // إنشاء سجل الموافقة في جدول ci_erp_notifications_approval
        StaffApproval::create([
            'company_id' => $application->company_id,
            'staff_id' => $approvedBy,
            'module_option' => 'leave_settings',
            'module_key_id' => $application->leave_id,
            'status' => LeaveApplication::STATUS_APPROVED,
            'approval_level' => 1,
            'updated_at' => now(),
        ]);

        $application->refresh();
        $application->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);

        return $application;
    }

    /**
     * رفض طلب إستئذان بالساعات
     */
    public function rejectHourlyLeave(LeaveApplication $application, int $rejectedBy, string $reason): LeaveApplication
    {
        $application->update([
            'status' => LeaveApplication::STATUS_REJECTED,
            'remarks' => $reason,
        ]);

        // إنشاء سجل الرفض في جدول ci_erp_notifications_approval
        StaffApproval::create([
            'company_id' => $application->company_id,
            'staff_id' => $rejectedBy,
            'module_option' => 'leave_settings',
            'module_key_id' => $application->leave_id,
            'status' => LeaveApplication::STATUS_REJECTED,
            'approval_level' => 1,
            'updated_at' => now(),
        ]);

        $application->refresh();
        $application->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);

        return $application;
    }

    /**
     * التحقق من وجود استئذان في نفس التاريخ للموظف
     */
    public function hasHourlyLeaveOnDate(int $employeeId, string $date, int $companyId): bool
    {
        return LeaveApplication::where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('particular_date', $date) // نفس التاريخ المطلوب
            ->where('leave_hours', '>', 0) // ساعات أكبر من صفر
            ->where('leave_hours', '<', 8) // ساعات أقل من 8
            ->whereIn('status', [1, 2, 3]) // pending أو approved أو rejected 
            ->exists();
    }

    /**
     * تحديث طلب إستئذان بالساعات
     */
    public function updateHourlyLeave(LeaveApplication $application, UpdateHourlyLeaveDTO $dto): LeaveApplication
    {
        try {
            if ($dto->hasUpdates()) {
                $updates = $dto->toArray();

                // تحديث باستخدام Eloquent
                $application->update($updates);

                // تحديث البيانات
                $application->refresh();

                // تحميل العلاقات
                $application->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);
            }

            Log::debug('HourlyLeaveRepository::updateHourlyLeave - Update completed', [
                'application_id' => $application->leave_id
            ]);

            return $application;
        } catch (\Exception $e) {
            Log::error("HourlyLeaveRepository::updateHourlyLeave - Error: " . $e->getMessage());
            throw $e;
        }
    }
}
