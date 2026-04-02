<?php

declare(strict_types=1);

namespace App\DTOs\Training;

class UpdateTrainingDTO
{
    public function __construct(
        public readonly ?int $trainingTypeId = null,
        public readonly ?int $trainerId = null,
        public readonly ?array $employeeIds = null,
        public readonly ?string $startDate = null,
        public readonly ?string $finishDate = null,
        public readonly ?int $departmentId = null,
        public readonly ?string $associatedGoals = null,
        public readonly ?float $trainingCost = null,
        public readonly ?int $trainingStatus = null,
        public readonly ?string $description = null,
        public readonly ?int $performance = null,
        public readonly ?string $remarks = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        $employeeIds = null;
        if (isset($data['employee_ids'])) {
            $employeeIds = is_array($data['employee_ids'])
                ? $data['employee_ids']
                : explode(',', $data['employee_ids']);
        }

        return new self(
            trainingTypeId: isset($data['training_type_id']) ? (int) $data['training_type_id'] : null,
            trainerId: isset($data['trainer_id']) ? (int) $data['trainer_id'] : null,
            employeeIds: $employeeIds,
            startDate: $data['start_date'] ?? null,
            finishDate: $data['finish_date'] ?? null,
            departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            associatedGoals: $data['associated_goals'] ?? null,
            trainingCost: isset($data['training_cost']) ? (float) $data['training_cost'] : null,
            trainingStatus: isset($data['training_status']) ? (int) $data['training_status'] : null,
            description: $data['description'] ?? null,
            performance: isset($data['performance']) ? (int) $data['performance'] : null,
            remarks: $data['remarks'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->trainingTypeId !== null) $data['training_type_id'] = $this->trainingTypeId;
        if ($this->trainerId !== null) $data['trainer_id'] = $this->trainerId;
        if ($this->employeeIds !== null) $data['employee_id'] = implode(',', $this->employeeIds);
        if ($this->startDate !== null) $data['start_date'] = $this->startDate;
        if ($this->finishDate !== null) $data['finish_date'] = $this->finishDate;
        if ($this->departmentId !== null) $data['department_id'] = $this->departmentId;
        if ($this->associatedGoals !== null) $data['associated_goals'] = $this->associatedGoals;
        if ($this->trainingCost !== null) $data['training_cost'] = $this->trainingCost;
        if ($this->trainingStatus !== null) $data['training_status'] = $this->trainingStatus;
        if ($this->description !== null) $data['description'] = $this->description;
        if ($this->performance !== null) $data['performance'] = $this->performance;
        if ($this->remarks !== null) $data['remarks'] = $this->remarks;

        return $data;
    }
}
