<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'training_id' => $this->training_id,
            'company_id' => $this->company_id,
            'department_id' => $this->department_id,
            'department_name' => $this->department_name,
            'employee_ids' => $this->employee_ids_array,
            'training_type_id' => $this->training_type_id,
            'training_type_name' => $this->training_type_name,
            'trainer_id' => $this->trainer_id,
            'trainer_name' => $this->trainer_name,
            'start_date' => $this->start_date,
            'finish_date' => $this->finish_date,
            'training_cost' => $this->training_cost,
            'training_status' => $this->training_status,
            'status_label' => $this->status_label,
            'description' => $this->description,
            'performance' => $this->performance,
            'performance_label' => $this->performance_label,
            'associated_goals' => $this->associated_goals,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,

            // Include trainer information if loaded
            'trainer' => $this->when($this->relationLoaded('trainer') && $this->trainer, function () {
                return [
                    'trainer_id' => $this->trainer->trainer_id,
                    'first_name' => $this->trainer->first_name,
                    'last_name' => $this->trainer->last_name,
                    'full_name' => $this->trainer->full_name,
                    'email' => $this->trainer->email,
                    'contact_number' => $this->trainer->contact_number,
                ];
            }),

            // Include training type information if loaded
            'training_type' => $this->when($this->relationLoaded('trainingType') && $this->trainingType, function () {
                return [
                    'constants_id' => $this->trainingType->constants_id,
                    'category_name' => $this->trainingType->category_name,
                ];
            }),

            // Include department information if loaded
            'department' => $this->when($this->relationLoaded('department') && $this->department, function () {
                return [
                    'department_id' => $this->department->department_id,
                    'department_name' => $this->department->department_name,
                ];
            }),

            // Include notes if loaded
            'notes' => $this->when($this->relationLoaded('notes'), function () {
                return $this->notes->map(function ($note) {
                    return [
                        'training_note_id' => $note->training_note_id,
                        'training_note' => $note->training_note,
                        'employee_id' => $note->employee_id,
                        'employee_name' => $note->employee_name,
                        'created_at' => $note->created_at,
                    ];
                });
            }),
        ];
    }
}
