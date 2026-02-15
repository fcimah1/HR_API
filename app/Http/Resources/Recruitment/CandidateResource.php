<?php

namespace App\Http\Resources\Recruitment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'candidate_id' => $this->candidate_id,
            'company_id' => $this->company_id,
            'job' => $this->whenLoaded('job', function () {
                return [
                    'job_id' => $this->job->job_id,
                    'job_title' => $this->job->job_title,
                ];
            }),
            'designation' => $this->whenLoaded('designation', function () {
                return [
                    'designation_id' => $this->designation->designation_id,
                    'designation_name' => $this->designation->designation_name,
                ];
            }),
            'staff_id' => $this->staff_id,
            'staff_name' => $this->staff?->first_name . ' ' . $this->staff?->last_name, // Assuming User model has names
            'message' => $this->message,
            'job_resume' => $this->job_resume, // This should be the full URL
            'application_status' => $this->application_status,
            'application_status_label' => $this->application_status?->label(),
            'application_remarks' => $this->application_remarks,
            'created_at' => $this->created_at,
        ];
    }
}
