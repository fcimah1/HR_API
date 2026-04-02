<?php

declare(strict_types=1);

namespace App\DTOs\Event;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

class UpdateEventDTO extends Data
{
    public function __construct(
        public readonly ?array $employeeIds,
        public readonly ?string $eventTitle,
        public readonly ?string $eventDate,
        public readonly ?string $eventTime,
        public readonly ?string $eventNote,
        public readonly ?string $eventColor,
        public readonly ?int $isShowCalendar,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            employeeIds: $request->has('employee_ids') ? $request->input('employee_ids') : null,
            eventTitle: $request->input('event_title'),
            eventDate: $request->input('event_date'),
            eventTime: $request->input('event_time'),
            eventNote: $request->input('event_note'),
            eventColor: $request->input('event_color'),
            isShowCalendar: $request->has('is_show_calendar') ? (int) $request->input('is_show_calendar') : null,
        );
    }

    public function toArray(): array
    {
        $data = [];
        if (!is_null($this->employeeIds)) $data['employee_id'] = implode(',', $this->employeeIds);
        if (!is_null($this->eventTitle)) $data['event_title'] = $this->eventTitle;
        if (!is_null($this->eventDate)) $data['event_date'] = $this->eventDate;
        if (!is_null($this->eventTime)) $data['event_time'] = date('H:i', strtotime($this->eventTime));
        if (!is_null($this->eventNote)) $data['event_note'] = $this->eventNote;
        if (!is_null($this->eventColor)) $data['event_color'] = $this->eventColor;
        if (!is_null($this->isShowCalendar)) $data['is_show_calendar'] = $this->isShowCalendar;

        return $data;
    }
}
