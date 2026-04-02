<?php

declare(strict_types=1);

namespace App\DTOs\Meeting;

class CreateMeetingDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $employeeId,
        public readonly string $title,
        public readonly string $date,
        public readonly string $time,
        public readonly string $room,
        public readonly ?string $note,
        public readonly ?string $color = '#2655ff'
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            employeeId: $data['employee_id'] ?? '0',
            title: $data['meeting_title'],
            date: $data['meeting_date'],
            time: $data['meeting_time'],
            room: $data['meeting_room'],
            note: $data['meeting_note'] ?? null,
            color: $data['meeting_color'] ?? '#2655ff'
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'meeting_title' => $this->title,
            'meeting_date' => $this->date,
            'meeting_time' => $this->time,
            'meeting_room' => $this->room,
            'meeting_note' => $this->note,
            'meeting_color' => $this->color,
            'created_at' => date('d-m-Y H:i:s'),
        ];
    }
}
