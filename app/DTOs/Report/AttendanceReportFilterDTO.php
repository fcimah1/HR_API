<?php

declare(strict_types=1);

namespace App\DTOs\Report;

/**
 * DTO لفلترة تقارير الحضور والانصراف
 * Attendance Report Filter DTO
 */
class AttendanceReportFilterDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?int $employeeId = null,
        public readonly ?int $branchId = null,
        public readonly ?string $month = null, // YYYY-MM
        public ?string $startDate = null,
        public ?string $endDate = null,
        public readonly ?string $reportType = 'monthly', // monthly, first_last, time_records, date_range, timesheet
        public readonly ?string $status = null, // Present, Absent, Late, etc.
        public readonly ?array $employeeIds = [],
    ) {}

    /**
     * إنشاء DTO من Request
     */
    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            branchId: isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            month: $data['month'] ?? null,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            reportType: $data['report_type'] ?? 'monthly',
            status: $data['status'] ?? null,
            employeeIds: (isset($data['staffs']) && (in_array(-1, $data['staffs']) || in_array('-1', $data['staffs'])))
                ? []
                : ($data['staffs'] ?? []),
        );
    }

    /**
     * تحويل إلى مصفوفة
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'branch_id' => $this->branchId,
            'month' => $this->month,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'report_type' => $this->reportType,
            'status' => $this->status,
        ];
    }

    /**
     * الحصول على تاريخ بداية الشهر
     */
    public function getMonthStartDate(): ?string
    {
        if (!$this->month) {
            return null;
        }
        return $this->month . '-01';
    }

    /**
     * الحصول على تاريخ نهاية الشهر
     */
    public function getMonthEndDate(): ?string
    {
        if (!$this->month) {
            return null;
        }
        return date('Y-m-t', strtotime($this->month . '-01'));
    }
}
