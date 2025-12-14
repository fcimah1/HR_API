<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'complaint_id' => $this->complaint_id,
            'company_id' => $this->company_id,
            'complaint_from' => $this->complaint_from,
            'title' => $this->title,
            'complaint_date' => $this->complaint_date,
            'complaint_against' => $this->complaint_against,
            'description' => $this->description,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'status_text_en' => $this->status_text_en,
            'created_at' => $this->created_at,

            // معلومات الموظف المقدم للشكوى
            'employee_name' => $this->when(
                $this->relationLoaded('employee'),
                fn() => $this->employee ? ($this->employee->first_name . ' ' . $this->employee->last_name) : 'غير محدد'
            ),

            // معلومات الموظف إذا كانت محملة
            'employee' => $this->when($this->relationLoaded('employee'), function () {
                return $this->employee ? [
                    'user_id' => $this->employee->user_id,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                    'email' => $this->employee->email,
                    'full_name' => $this->employee->full_name,
                ] : null;
            }),
        ];
    }
}
