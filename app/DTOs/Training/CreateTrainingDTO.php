<?php

declare(strict_types=1);

namespace App\DTOs\Training;

class CreateTrainingDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $trainingTypeId,
        public readonly int $trainerId,
        public readonly array $employeeIds,
        public readonly string $startDate,
        public readonly string $finishDate,
        public readonly ?int $departmentId = null,
        public readonly ?string $associatedGoals = null,
        public readonly ?float $trainingCost = null,
        public readonly int $trainingStatus = 0,
        public readonly ?string $description = null,
        public readonly ?int $performance = null,
        public readonly ?string $remarks = null,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            trainingTypeId: (int) $data['training_type_id'],
            trainerId: (int) $data['trainer_id'],
            employeeIds: is_array($data['employee_ids'])
                ? $data['employee_ids']
                : explode(',', $data['employee_ids']),
            startDate: $data['start_date'],
            finishDate: $data['finish_date'],
            departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            associatedGoals: $data['associated_goals'] ?? null,
            trainingCost: isset($data['training_cost']) ? (float) $data['training_cost'] : null,
            trainingStatus: (int) ($data['training_status'] ?? 0),
            description: $data['description'] ?? null,
            performance: isset($data['performance']) ? (int) $data['performance'] : null,
            remarks: $data['remarks'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'department_id' => $this->departmentId,
            'employee_id' => implode(',', $this->employeeIds),
            'training_type_id' => $this->trainingTypeId,
            'associated_goals' => $this->associatedGoals,
            'trainer_id' => $this->trainerId,
            'start_date' => $this->startDate,
            'finish_date' => $this->finishDate,
            'training_cost' => $this->trainingCost,
            'training_status' => $this->trainingStatus,
            'description' => $this->description,
            'performance' => $this->performance,
            'remarks' => $this->remarks,
            'created_at' => now()->format('d-m-Y H:i:s'),
        ];
    }
}
