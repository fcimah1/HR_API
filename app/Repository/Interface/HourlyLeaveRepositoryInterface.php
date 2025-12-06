<?php

namespace App\Repository\Interface;

use App\DTOs\Leave\CreateHourlyLeaveDTO;
use App\DTOs\Leave\HourlyLeaveFilterDTO;
use App\DTOs\Leave\UpdateHourlyLeaveDTO;
use App\Models\LeaveApplication;
use App\Models\User;

interface HourlyLeaveRepositoryInterface
{
    /**
     * الحصول على قائمة طلبات الإستئذان بالساعات مع التصفية والترقيم الصفحي
     */
    public function getPaginatedHourlyLeaves(HourlyLeaveFilterDTO $filters, User $user): array;

    /**
     * الحصول على طلب إستئذان بالساعات بواسطة المعرف
     */
    public function findHourlyLeaveById(int $id, int $companyId): ?LeaveApplication;

    /**
     * الحصول على طلب إستئذان بالساعات للموظف
     */
    public function findHourlyLeaveForEmployee(int $id, int $employeeId): ?LeaveApplication;

    /**
     * إنشاء طلب إستئذان بالساعات
     */
    public function createHourlyLeave(CreateHourlyLeaveDTO $dto): LeaveApplication;

    /**
     * إلغاء طلب إستئذان بالساعات
     */
    public function cancelHourlyLeave(LeaveApplication $application, int $cancelledBy, string $reason): LeaveApplication;

    /**
     * الموافقة على طلب إستئذان بالساعات
     */
    public function approveHourlyLeave(LeaveApplication $application, int $approvedBy, ?string $remarks = null): LeaveApplication;

    /**
     * رفض طلب إستئذان بالساعات
     */
    public function rejectHourlyLeave(LeaveApplication $application, int $rejectedBy, string $reason): LeaveApplication;

    /**
     * التحقق من وجود استئذان في نفس التاريخ للموظف
     */
    public function hasHourlyLeaveOnDate(int $employeeId, string $date, int $companyId): bool;

    /**
     * تحديث طلب إستئذان بالساعات
     */
    public function updateHourlyLeave(LeaveApplication $application, UpdateHourlyLeaveDTO $dto): LeaveApplication;
}

