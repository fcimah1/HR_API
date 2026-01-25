<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeListResource extends JsonResource
{
    /**
     * Transform the resource into an array for list view.
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'employee_id' => $this->user_details?->employee_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => "{$this->first_name} {$this->last_name}",
            'email' => $this->email,
            'contact_number' => $this->contact_number,
            'profile_photo' => $this->profile_photo ? url("storage/{$this->profile_photo}") : null,
            
            // معلومات أساسية للقائمة
            'department_id' => $this->user_details?->department_id,
            'department_name' => $this->user_details?->department?->department_name,
            'designation_id' => $this->user_details?->designation_id,
            'designation_name' => $this->user_details?->designation?->designation_name,
            'hierarchy_level' => $this->user_details?->designation?->hierarchy_level ?? 5,
            
            'basic_salary' => $this->user_details?->basic_salary,
            'currency' => $this->user_details?->currency,
            'date_of_joining' => $this->user_details?->date_of_joining,
            
            'is_active' => (bool) $this->is_active,
            'last_login_date' => $this->last_login_date,
        ];
    }
}