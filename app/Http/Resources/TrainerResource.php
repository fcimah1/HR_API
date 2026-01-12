<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'trainer_id' => $this->trainer_id,
            'company_id' => $this->company_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'expertise' => $this->expertise,
            'address' => $this->address,
            'trainings_count' => $this->trainings_count,
            'created_at' => $this->created_at,

            // Include trainings if loaded
            'trainings' => $this->when($this->relationLoaded('trainings'), function () {
                return $this->trainings->map(function ($training) {
                    return [
                        'training_id' => $training->training_id,
                        'training_type_name' => $training->training_type_name,
                        'start_date' => $training->start_date,
                        'finish_date' => $training->finish_date,
                        'training_status' => $training->training_status,
                        'status_label' => $training->status_label,
                    ];
                });
            }),
        ];
    }
}
