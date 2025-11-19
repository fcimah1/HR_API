<?php

namespace App\DTOs\Leave;

class CreateLeaveTypeDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $name,
        public readonly bool $requiresApproval = true,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        $requiresApproval = array_key_exists('requires_approval', $data)
            ? (bool) $data['requires_approval']
            : true;

        return new self(
            companyId: $companyId,
            name: $data['leave_type_name'],
            requiresApproval: $requiresApproval,
        );
    }

    public function toArray(): array
    {
        $options = [
            'requires_approval' => $this->requiresApproval ? 1 : 0,
        ];

        return [
            'company_id' => $this->companyId,
            'type' => 'leave_type',
            'category_name' => $this->name,
            'field_one' => serialize($options),
            'field_two' => '1',
            'field_three' => '1',
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}

