<?php

namespace App\DTOs\Promotion;

class CreatePromotionDTO
{
    public function __construct(
        public int $companyId,
        public int $employeeId,
        public string $promotionTitle,
        public string $promotionDate,
        public int $newDesignationId,
        public int $newDepartmentId,
        public float $newSalary,
        public ?string $description = null,
        public ?array $notifySendTo = null,
        public ?int $addedBy = null
    ) {}

    public static function fromRequest(array $data, int $companyId, int $addedBy): self
    {
        return new self(
            companyId: $companyId,
            employeeId: (int) $data['employee_id'],
            promotionTitle: $data['promotion_title'],
            promotionDate: $data['promotion_date'],
            newDesignationId: (int) $data['new_designation_id'],
            newDepartmentId: (int) $data['new_department_id'],
            newSalary: (float) $data['new_salary'],
            description: $data['description'] ?? null,
            notifySendTo: $data['notify_send_to'] ?? null,
            addedBy: $addedBy
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'promotion_title' => $this->promotionTitle,
            'promotion_date' => $this->promotionDate,
            'new_designation_id' => $this->newDesignationId,
            'new_department_id' => $this->newDepartmentId,
            'new_salary' => $this->newSalary,
            'description' => $this->description,
            'notify_send_to' => $this->notifySendTo,
            'added_by' => $this->addedBy,
            'status' => 0, // Pending
            'created_at' => now(),
        ];
    }
}
