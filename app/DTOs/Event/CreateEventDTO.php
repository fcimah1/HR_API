<?php

declare(strict_types=1);

namespace App\DTOs\Event;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

class CreateEventDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?array $employeeIds,
        public readonly string $eventTitle,
        public readonly string $eventDate,
        public readonly string $eventTime,
        public readonly ?string $eventNote,
        public readonly ?string $eventColor,
        public readonly int $isShowCalendar,
    ) {}

    public static function fromRequest(Request $request, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            employeeIds: $request->input('employee_ids'),
            eventTitle: (string) $request->input('event_title'),
            eventDate: (string) $request->input('event_date'),
            eventTime: (string) $request->input('event_time'),
            eventNote: $request->input('event_note'),
            eventColor: $request->input('event_color', '#7267EF'),
            isShowCalendar: (int) $request->input('is_show_calendar', 0),
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => !empty($this->employeeIds) ? implode(',', $this->employeeIds) : null,
            'event_title' => $this->eventTitle,
            'event_date' => $this->eventDate,
            'event_time' => !empty($this->eventTime) ? date('H:i', strtotime($this->eventTime)) : null,
            'event_note' => $this->eventNote,
            'event_color' => $this->eventColor,
            'is_show_calendar' => $this->isShowCalendar,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}
