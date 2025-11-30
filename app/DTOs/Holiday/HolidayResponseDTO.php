<?php

namespace App\DTOs\Holiday;

use App\Models\Holiday;

class HolidayResponseDTO
{
    public function __construct(
        public readonly int $holidayId,
        public readonly int $companyId,
        public readonly string $eventName,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?string $description,
        public readonly int $isPublish,
        public readonly ?string $createdAt,
    ) {}

    public static function fromModel(Holiday $holiday): self
    {
        return new self(
            holidayId: $holiday->holiday_id,
            companyId: $holiday->company_id,
            eventName: $holiday->event_name,
            startDate: $holiday->start_date->format('Y-m-d'),
            endDate: $holiday->end_date->format('Y-m-d'),
            description: $holiday->description,
            isPublish: $holiday->is_publish,
            createdAt: $holiday->created_at,
        );
    }

    public function toArray(): array
    {
        return [
            'holiday_id' => $this->holidayId,
            'company_id' => $this->companyId,
            'event_name' => $this->eventName,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'description' => $this->description,
            'is_publish' => $this->isPublish,
            'created_at' => $this->createdAt,
        ];
    }
}
