<?php

namespace App\DTOs\Promotion;

class UpdatePromotionDTO
{
    public function __construct(
        public ?string $promotionTitle = null,
        public ?string $promotionDate = null,
        public ?int $newDesignationId = null,
        public ?int $newDepartmentId = null,
        public ?float $newSalary = null,
        public ?string $description = null,
        public ?array $notifySendTo = null,
        public ?int $status = null
    ) {}

    public static function fromRequest(array $data): self
    {
        $status = null;
        if (isset($data['status'])) {
            foreach (\App\Enums\NumericalStatusEnum::cases() as $case) {
                if (ucfirst(strtolower($case->name)) === $data['status']) {
                    $status = $case->value;
                    break;
                }
            }
        }

        return new self(
            promotionTitle: $data['promotion_title'] ?? null,
            promotionDate: $data['promotion_date'] ?? null,
            newDesignationId: isset($data['new_designation_id']) ? (int) $data['new_designation_id'] : null,
            newDepartmentId: isset($data['new_department_id']) ? (int) $data['new_department_id'] : null,
            newSalary: isset($data['new_salary']) ? (float) $data['new_salary'] : null,
            description: $data['description'] ?? null,
            notifySendTo: $data['notify_send_to'] ?? null,
            status: $status
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'promotion_title' => $this->promotionTitle,
            'promotion_date' => $this->promotionDate,
            'new_designation_id' => $this->newDesignationId,
            'new_department_id' => $this->newDepartmentId,
            'new_salary' => $this->newSalary,
            'description' => $this->description,
            'notify_send_to' => $this->notifySendTo,
            'status' => $this->status,
        ], fn($value) => !is_null($value));
    }
}
