<?php

declare(strict_types=1);

namespace App\DTOs\Training;

use App\Enums\TrainingStatusEnum;

class TrainingFilterDTO
{
    public function __construct(
        public readonly ?int $companyId = null,
        public readonly ?int $employeeId = null,
        public readonly ?array $employeeIds = null,
        public readonly ?int $trainerId = null,
        public readonly ?int $trainingTypeId = null,
        public readonly ?int $departmentId = null,
        public readonly ?int $status = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $search = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc'
    ) {}

    public static function fromRequest(array $data): self
    {
        $status = null;
        if (isset($data['status']) && $data['status'] !== null && $data['status'] !== '') {
            if (is_numeric($data['status'])) {
                $status = (int) $data['status'];
            } else {
                $statusMap = [
                    'pending' => TrainingStatusEnum::PENDING->value,
                    'started' => TrainingStatusEnum::STARTED->value,
                    'completed' => TrainingStatusEnum::COMPLETED->value,
                    'rejected' => TrainingStatusEnum::REJECTED->value,
                ];
                $status = $statusMap[strtolower($data['status'])] ?? null;
            }
        }

        return new self(
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            employeeIds: $data['employee_ids'] ?? null,
            trainerId: isset($data['trainer_id']) ? (int) $data['trainer_id'] : null,
            trainingTypeId: isset($data['training_type_id']) ? (int) $data['training_type_id'] : null,
            departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            status: $status,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            search: $data['search'] ?? null,
            perPage: (int) ($data['per_page'] ?? 15),
            page: (int) ($data['page'] ?? 1),
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc'
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'employee_ids' => $this->employeeIds,
            'trainer_id' => $this->trainerId,
            'training_type_id' => $this->trainingTypeId,
            'department_id' => $this->departmentId,
            'status' => $this->status,
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
