<?php

namespace App\DTOs\Leave;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class LeaveApplicationFilterDTO
{
    public function __construct(
        public readonly ?string $companyName = null,
        public readonly ?int $companyId = null,
        public readonly ?int $employeeId = null,
        public readonly ?array $employeeIds = null,
        public readonly ?array $hierarchyLevels = null, // إضافة فلترة المستويات الهرمية
        public readonly ?int $status = null, // 1 for pending, 2 for approved, 3 for rejected
        public readonly ?int $leaveTypeId = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $search = null, // إضافة معامل البحث
        public readonly int $perPage = 15,
        public readonly int $page = 1,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc'
    ) {}

    public static function fromRequest(array $data): self
    {
        // Handle status conversion properly
        $status = null;
        // status can be string (pending/approved/rejected) or int (1/2/3)
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            if ($data['status'] === 'approved' || $data['status'] === 2) {
                $status = 2;
            } else if ($data['status'] === 'rejected' || $data['status'] === 3) {
                $status = 3;
            } else if ($data['status'] === 'pending' || $data['status'] === 1) {
                $status = 1;
            } else {
                $status = null;
            }
        }

        return new self(
            companyName: $data['company_name'] ?? null,
            companyId: $data['company_id'] ?? null,
            employeeId: $data['employee_id'] ?? null,
            employeeIds: $data['employee_ids'] ?? null,
            hierarchyLevels: $data['hierarchy_levels'] ?? null, // إضافة فلترة المستويات الهرمية
            status: $status,
            leaveTypeId: $data['leave_type_id'] ?? null,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            search: $data['search'] ?? null, // إضافة معامل البحث
            perPage: (int) ($data['per_page'] ?? 15),
            page: (int) ($data['page'] ?? 1),
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc'
        );
    }

    public function toArray(): array
    {
        return [
            'company_name' => $this->companyName,
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'employee_ids' => $this->employeeIds,
            'hierarchy_levels' => $this->hierarchyLevels, // إضافة فلترة المستويات الهرمية
            'status' => $this->status,
            'leave_type_id' => $this->leaveTypeId,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'search' => $this->search,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];
    }
}
