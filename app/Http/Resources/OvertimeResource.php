<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OvertimeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'time_request_id' => $this->time_request_id,
            'company_id' => $this->company_id,
            'staff_id' => $this->staff_id,
            'request_date' => $this->request_date,
            'request_month' => $this->request_month,
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'total_hours' => $this->total_hours,
            'request_reason' => $this->request_reason,
            'is_approved' => $this->is_approved,
            'status_text' => $this->status_text,
            'overtime_reason' => $this->overtime_reason,
            'overtime_reason_text' => $this->overtime_reason_text,
            'additional_work_hours' => $this->additional_work_hours,
            'straight' => $this->straight,
            'time_a_half' => $this->time_a_half,
            'double_overtime' => $this->double_overtime,
            'compensation_type' => $this->compensation_type,
            'compensation_type_text' => $this->compensation_type_text,
            'compensation_banked' => $this->compensation_banked,
            'created_at' => $this->created_at,
            
            // Include employee information if loaded
            'employee' => $this->when($this->relationLoaded('employee'), function () {
                return [
                    'user_id' => $this->employee->user_id,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                    'email' => $this->employee->email,
                    'full_name' => $this->employee->full_name,
                ];
            }),
            
            // Include approval information if loaded
            'approvals' => $this->when($this->relationLoaded('approvals'), function () {
                return $this->approvals->map(function ($approval) {
                    return [
                        'staff_approval_id' => $approval->staff_approval_id,
                        'staff_id' => $approval->staff_id,
                        'staff_name' => $approval->staff ? $approval->staff->full_name : null,
                        'status' => $approval->status,
                        'approval_level' => $approval->approval_level,
                        'updated_at' => $approval->updated_at,
                    ];
                });
            }),
        ];
    }
}

