<?php

namespace App\DTOs\Leave;

class ApproveOrRejectHourlyLeaveDTO
{
    public function __construct(
        public readonly int $hourlyLeaveId,
        public readonly string $action, // 'approve' or 'reject'
        public readonly int $processedBy,
        public readonly ?string $remarks = null
    ) {}

    public static function fromRequest(array $data, int $hourlyLeaveId, int $processedBy): self
    {
        return new self(
            hourlyLeaveId: $hourlyLeaveId,
            action: $data['action'],
            processedBy: $processedBy,
            remarks: $data['remarks'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'hourly_leave_id' => $this->hourlyLeaveId,
            'action' => $this->action,
            'processed_by' => $this->processedBy,
            'remarks' => $this->remarks,
        ];
    }
}

