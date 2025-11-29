<?php

namespace App\DTOs\Attendance;

class AttendanceFilterDTO
{
    public function __construct(
        public readonly ?int $employeeId = null,
        public readonly ?int $companyId = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $status = null,
        public readonly ?int $workFromHome = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1
    ) {}

    public static function fromRequest(array $data, ?int $companyId = null): self
    {
        return new self(
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            companyId: $companyId,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            status: $data['status'] ?? null,
            workFromHome: isset($data['work_from_home']) ? (int) $data['work_from_home'] : null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
            page: isset($data['page']) ? (int) $data['page'] : 1
        );
    }
}
