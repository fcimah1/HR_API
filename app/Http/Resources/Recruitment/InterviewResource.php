<?php

namespace App\Http\Resources\Recruitment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'job_interview_id' => $this->job_interview_id,
            'company_id' => $this->company_id,
            'job' => $this->whenLoaded('job', function () {
                return [
                    'job_id' => $this->job->job_id,
                    'job_title' => $this->job->job_title,
                ];
            }),
            'candidate' => $this->whenLoaded('candidate', function () {
                return [
                    'candidate_id' => $this->candidate->candidate_id,
                    'staff_id' => $this->candidate->staff_id,
                    'name' => $this->candidate->staff ? ($this->candidate->staff->first_name . ' ' . $this->candidate->staff->last_name) : null,
                ];
            }),
            'staff' => $this->whenLoaded('staff', function () {
                return [
                    'user_id' => $this->staff->user_id,
                    'name' => $this->staff->first_name . ' ' . $this->staff->last_name,
                ];
            }),
            'interviewer' => $this->whenLoaded('interviewer', function () {
                return [
                    'user_id' => $this->interviewer->user_id,
                    'name' => $this->interviewer->first_name . ' ' . $this->interviewer->last_name,
                ];
            }),
            'interview_place' => $this->interview_place,
            'interview_date' => $this->interview_date->format('Y-m-d'),
            'interview_time' => $this->interview_time,
            'description' => $this->description,
            'interview_remarks' => $this->interview_remarks,
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'created_at' => $this->created_at,
        ];
    }
}
