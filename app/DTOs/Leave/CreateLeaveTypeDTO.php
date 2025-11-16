<?php

namespace App\DTOs\Leave;

class CreateLeaveTypeDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $name,
        public readonly ?string $shortName = null,
        public readonly int $days = 0
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            name: $data['leave_type_name'],
            shortName: $data['leave_type_short_name'] ?? null,
            days: $data['leave_days']
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'type' => 'leave_type',
            'category_name' => $this->name,
            'field_one' => $this->shortName,
            'field_two' => (string) $this->days,
            'field_three' => '1',
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}

