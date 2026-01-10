<?php

declare(strict_types=1);

namespace App\DTOs\InternalHelpdesk;

class InternalTicketFilterDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly bool $isCompanyOwner,
        public readonly ?int $userId,
        public readonly ?array $allowedUserIds, // قائمة المستخدمين المسموح رؤية تذاكرهم
        public readonly ?int $status,
        public readonly ?int $priority,
        public readonly ?int $departmentId,
        public readonly ?int $employeeId,
        public readonly ?string $search,
        public readonly ?string $fromDate,
        public readonly ?string $toDate,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(
        array $data,
        int $companyId,
        bool $isCompanyOwner,
        ?int $userId,
        ?array $allowedUserIds = null
    ): self {
        return new self(
            companyId: $companyId,
            isCompanyOwner: $isCompanyOwner,
            userId: $userId,
            allowedUserIds: $allowedUserIds,
            status: isset($data['status']) ? (int)$data['status'] : null,
            priority: isset($data['priority']) ? (int)$data['priority'] : null,
            departmentId: isset($data['department_id']) ? (int)$data['department_id'] : null,
            employeeId: isset($data['employee_id']) ? (int)$data['employee_id'] : null,
            search: $data['search'] ?? null,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            page: isset($data['page']) ? (int)$data['page'] : 1,
            perPage: isset($data['per_page']) ? (int)$data['per_page'] : 15,
        );
    }
}
