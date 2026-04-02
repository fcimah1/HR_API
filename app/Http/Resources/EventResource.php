<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray($request): array
    {
        // employee_id is a comma-separated string in DB
        $employeeIds = $this->employee_id ? explode(',', $this->employee_id) : [];

        // We could fetch employee names here, but for simplicity we return IDs.
        // If the frontend needs names, we can join with User model.

        return [
            'event_id' => $this->event_id,
            'company_id' => $this->company_id,
            'event_title' => $this->event_title,
            'employee_ids' => $employeeIds,
            'event_date' => $this->event_date,
            'event_time' => $this->event_time,
            'event_note' => $this->event_note,
            'event_color' => $this->event_color,
            'is_show_calendar' => (int) $this->is_show_calendar,
            'created_at' => $this->created_at,
        ];
    }
}
