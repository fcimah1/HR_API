<?php

namespace App\Http\Resources\Recruitment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'job_id' => $this->job_id,
            'company_id' => $this->company_id,
            'job_title' => $this->job_title,
            'designation' => $this->whenLoaded('designation', function () {
                return [
                    'designation_id' => $this->designation->designation_id,
                    'designation_name' => $this->designation->designation_name,
                ];
            }),
            'job_type' => $this->job_type,
            'job_type_label' => $this->job_type?->trans(),
            'job_vacancy' => $this->job_vacancy,
            'gender' => $this->gender,
            'gender_label' => $this->gender?->label(),
            'minimum_experience' => $this->minimum_experience,
            'minimum_experience_label' => $this->minimum_experience?->label(),
            'date_of_closing' => $this->date_of_closing->format('Y-m-d'),
            'short_description' => $this->short_description,
            'long_description' => $this->long_description,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'created_at' => $this->created_at,
        ];
    }
}
