<?php

namespace App\DTOs\Leave;

class CancelHourlyLeaveDTO
{
    public function __construct(
        public readonly int $hourlyLeaveId,
        public readonly int $cancelledBy,
        public readonly string $reason
    ) {}

    public static function fromRequest(array $data, int $hourlyLeaveId, int $cancelledBy): self
    {
        return new self(
            hourlyLeaveId: $hourlyLeaveId,
            cancelledBy: $cancelledBy,
            reason: $data['reason'] ?? 'تم إلغاء الطلب من قبل الموظف'
        );
    }

    public function toArray(): array
    {
        return [
            'hourly_leave_id' => $this->hourlyLeaveId,
            'cancelled_by' => $this->cancelledBy,
            'reason' => $this->reason,
        ];
    }
}

