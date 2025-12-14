<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResignationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'resignation_id' => $this->resignation_id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'notice_date' => $this->notice_date,
            'resignation_date' => $this->resignation_date,
            'document_file' => $this->document_file,
            'is_signed' => $this->is_signed,
            'signed_file' => $this->signed_file,
            'signed_date' => $this->signed_date,
            'reason' => $this->reason,
            'added_by' => $this->added_by,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'status_text_en' => $this->status_text_en,
            'created_at' => $this->created_at,

            // معلومات الموظف المستقيل
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

            // معلومات من أضاف الطلب
            'added_by_name' => $this->when(
                $this->relationLoaded('addedBy'),
                fn() => $this->addedBy ? ($this->addedBy->first_name . ' ' . $this->addedBy->last_name) : 'غير محدد'
            ),
        ];
    }
}
