<?php

namespace App\DTOs\Overtime;

class OvertimeStatsDTO
{
    public function __construct(
        public readonly int $totalRequests,
        public readonly int $pendingRequests,
        public readonly int $approvedRequests,
        public readonly int $rejectedRequests,
        public readonly string $totalOvertimeHours,
        public readonly string $approvedOvertimeHours,
        public readonly array $byReason = [],
        public readonly array $byCompensationType = [],
    ) {}

    /**
     * Create DTO from calculated data.
     */
    public static function fromData(array $data): self
    {
        return new self(
            totalRequests: $data['total_requests'] ?? 0,
            pendingRequests: $data['pending_requests'] ?? 0,
            approvedRequests: $data['approved_requests'] ?? 0,
            rejectedRequests: $data['rejected_requests'] ?? 0,
            totalOvertimeHours: $data['total_overtime_hours'] ?? '0:00',
            approvedOvertimeHours: $data['approved_overtime_hours'] ?? '0:00',
            byReason: $data['by_reason'] ?? [],
            byCompensationType: $data['by_compensation_type'] ?? [],
        );
    }

    /**
     * Convert DTO to array.
     */
    public function toArray(): array
    {
        return [
            'total_requests' => $this->totalRequests,
            'pending_requests' => $this->pendingRequests,
            'approved_requests' => $this->approvedRequests,
            'rejected_requests' => $this->rejectedRequests,
            'total_overtime_hours' => $this->totalOvertimeHours,
            'approved_overtime_hours' => $this->approvedOvertimeHours,
            'by_reason' => $this->byReason,
            'by_compensation_type' => $this->byCompensationType,
        ];
    }
}

