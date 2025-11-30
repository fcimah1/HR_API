<?php

namespace App\DTOs\Holiday;

use Illuminate\Http\Request;
use Carbon\Carbon;

class CreateHolidayDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $eventName,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?string $description = null,
        public readonly int $isPublish = 1,
    ) {}

    public static function fromRequest(Request $request, int $companyId): self
    {
        return new self(
            companyId: $companyId,
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
            'company_id' => $this->companyId,
            'event_name' => $this->eventName,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'description' => $this->description,
            'is_publish' => $this->isPublish,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}
