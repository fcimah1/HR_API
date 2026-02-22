<?php

namespace App\Repository;

use App\DTOs\Leave\CreateHourlyLeaveDTO;
use App\DTOs\Leave\HourlyLeaveFilterDTO;
use App\DTOs\Leave\UpdateHourlyLeaveDTO;
use App\Models\LeaveApplication;
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

        // فلتر طلبات الإجازة بالساعات:
        // leave_hours > 0  → استئذان وليس إجازة منعدمة
        // leave_hours < hours_per_day للموظف → استئذان (أقل من يوم كامل)
        // نستخدم JOIN مع شفت الموظف ونستعمل COALESCE للفول-باك على 8 إذا لم يوجد شفت
        $query = LeaveApplication::where('ci_leave_applications.company_id', $companyId)
            ->join('ci_erp_users_details', 'ci_leave_applications.employee_id', '=', 'ci_erp_users_details.user_id')
            ->leftJoin('ci_office_shifts', 'ci_erp_users_details.office_shift_id', '=', 'ci_office_shifts.office_shift_id')
            ->select('ci_leave_applications.*') // نختار بيانات الإجازة فقط لتجنب تداخل المعرفات
            ->where('ci_leave_applications.leave_hours', '>', 0) // ساعات أكبر من صفر
            ->whereRaw('CAST(ci_leave_applications.leave_hours AS DECIMAL(10,2)) < CASE WHEN (COALESCE(ci_office_shifts.hours_per_day, 0) > 0) THEN ci_office_shifts.hours_per_day ELSE 8 END')
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

        // Exclude restricted leave types
        if ($filters->excludedLeaveTypeIds !== null && !empty($filters->excludedLeaveTypeIds)) {
            $query->whereNotIn('leave_type_id', $filters->excludedLeaveTypeIds);
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
        return LeaveApplication::where('ci_leave_applications.company_id', $companyId)
            ->join('ci_erp_users_details', 'ci_leave_applications.employee_id', '=', 'ci_erp_users_details.user_id')
            ->leftJoin('ci_office_shifts', 'ci_erp_users_details.office_shift_id', '=', 'ci_office_shifts.office_shift_id')
            ->select('ci_leave_applications.*')
            ->where('ci_leave_applications.leave_hours', '>', 0)
            ->whereRaw('CAST(ci_leave_applications.leave_hours AS UNSIGNED) < COALESCE(ci_office_shifts.hours_per_day, 8)')
            ->with(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff'])
            ->find($id);
    }

    /**
     * الحصول على طلب إستئذان بالساعات للموظف
     */
    public function findHourlyLeaveForEmployee(int $id, int $employeeId): ?LeaveApplication
    {
        return LeaveApplication::where('ci_leave_applications.employee_id', $employeeId)
            ->join('ci_erp_users_details', 'ci_leave_applications.employee_id', '=', 'ci_erp_users_details.user_id')
            ->leftJoin('ci_office_shifts', 'ci_erp_users_details.office_shift_id', '=', 'ci_office_shifts.office_shift_id')
            ->select('ci_leave_applications.*')
            ->where('ci_leave_applications.leave_hours', '>', 0)
            ->whereRaw('CAST(ci_leave_applications.leave_hours AS UNSIGNED) < COALESCE(ci_office_shifts.hours_per_day, 8)')
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

        // Note: Cancellation recording is handled by ApprovalService to avoid duplicates

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

        // Note: Approval recording is handled by ApprovalService to avoid duplicates

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

        // Note: Rejection recording is handled by ApprovalService to avoid duplicates

        $application->refresh();
        $application->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);

        return $application;
    }

    /**
     * التحقق من وجود استئذان في نفس التاريخ للموظف
     */
    public function hasHourlyLeaveOnDate(int $employeeId, string $date, int $companyId): bool
    {
        // نجلب ساعات شفت الموظف مباشرةً ولا نستخدم رقماً ثابتاً
        $employee = User::with('user_details.officeShift')->find($employeeId);
        $shiftHours = $employee ? (float) ($employee->user_details?->first()?->officeShift?->hours_per_day ?? 8) : 8.0;

        return LeaveApplication::where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('particular_date', $date)
            ->where('leave_hours', '>', 0)
            ->where('leave_hours', '<', $shiftHours) // أقل من ساعات شفت الموظف
            ->whereIn('status', [
                LeaveApplication::STATUS_PENDING,
                LeaveApplication::STATUS_APPROVED,
                LeaveApplication::STATUS_REJECTED
            ])
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
