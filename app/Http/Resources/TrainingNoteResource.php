<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'training_note_id' => $this->training_note_id,
            'training_id' => $this->training_id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee_name,
            'training_note' => $this->training_note,
            'created_at' => $this->created_at,

            // Include employee if loaded
            'employee' => $this->when($this->relationLoaded('employee') && $this->employee, function () {
                return [
                    'staff_id' => $this->employee->staff_id,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                    'full_name' => $this->employee->full_name,
                ];
            }),
        ];
    }
}
