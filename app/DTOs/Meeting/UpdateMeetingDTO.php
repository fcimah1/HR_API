<?php

declare(strict_types=1);

namespace App\DTOs\Meeting;

class UpdateMeetingDTO
{
    public function __construct(
        public readonly ?string $employeeId = null,
        public readonly ?string $title = null,
        public readonly ?string $date = null,
        public readonly ?string $time = null,
        public readonly ?string $room = null,
        public readonly ?string $note = null,
        public readonly ?string $color = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            employeeId: $data['employee_id'] ?? null,
            title: $data['meeting_title'] ?? null,
            date: $data['meeting_date'] ?? null,
            time: $data['meeting_time'] ?? null,
            room: $data['meeting_room'] ?? null,
            note: $data['meeting_note'] ?? null,
            color: $data['meeting_color'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'employee_id' => $this->employeeId,
            'meeting_title' => $this->title,
            'meeting_date' => $this->date,
            'meeting_time' => $this->time,
            'meeting_room' => $this->room,
            'meeting_note' => $this->note,
            'meeting_color' => $this->color,
        ], fn($value) => $value !== null);
    }
}
