<?php

namespace App\DTOs\Holiday;

use Illuminate\Http\Request;
use Carbon\Carbon;

class UpdateHolidayDTO
{
    public function __construct(
        public readonly string $eventName,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?string $description = null,
        public readonly int $isPublish = 1,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            eventName: $request->input('event_name'),
            startDate: Carbon::parse($request->input('start_date'))->format('Y-m-d'),
            endDate: Carbon::parse($request->input('end_date'))->format('Y-m-d'),
            description: $request->input('description'),
            isPublish: (int)($request->input('is_publish', 1)),
        );
    }

    public function toArray(): array
    {
        return [
            'event_name' => $this->eventName,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'description' => $this->description,
            'is_publish' => $this->isPublish,
        ];
    }
}
