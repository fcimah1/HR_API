<?php

namespace App\DTOs\Announcement;

/**
 * @OA\Schema(
 *     title="AnnouncementFilterDTO",
 *     description="معايير تصفية الإعلانات"
 * )
 */
class AnnouncementFilterDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?string $search = null,
        public readonly ?bool $status = null,
        public readonly ?int $targetDepartmentId = null,
        public readonly ?int $targetEmployeeId = null,
        public readonly bool $paginate = true,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            search: $data['search'] ?? null,
            status: isset($data['status']) ? filter_var($data['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
            targetDepartmentId: isset($data['target_department_id']) ? (int) $data['target_department_id'] : null,
            targetEmployeeId: isset($data['target_employee_id']) ? (int) $data['target_employee_id'] : null,
            paginate: filter_var($data['paginate'] ?? true, FILTER_VALIDATE_BOOLEAN),
            page: isset($data['page']) ? (int) $data['page'] : 1,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }
}
