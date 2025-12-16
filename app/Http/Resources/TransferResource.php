<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'transfer_id' => $this->transfer_id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'old_salary' => $this->old_salary,
            'old_designation' => $this->old_designation,
            'old_department' => $this->old_department,
            'transfer_date' => $this->transfer_date,
            'transfer_department' => $this->transfer_department,
            'transfer_designation' => $this->transfer_designation,
            'new_salary' => $this->new_salary,
            'old_company_id' => $this->old_company_id,
            'old_branch_id' => $this->old_branch_id,
            'new_company_id' => $this->new_company_id,
            'new_branch_id' => $this->new_branch_id,
            'old_currency' => $this->old_currency,
            'new_currency' => $this->new_currency,
            'reason' => $this->reason,
            'document_file' => $this->document_file ?? null,
            'document_file_url' => $this->document_file
                ? env('SHARED_UPLOADS_URL', url('uploads')) . '/pdf_files/transfer/' . $this->document_file
                : null,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'status_text_en' => $this->status_text_en,

            // معلومات نوع النقل
            'transfer_type' => $this->transfer_type,
            'transfer_type_text' => $this->transfer_type_text,
            'transfer_type_text_en' => $this->transfer_type_text_en,

            // حالات الموافقة
            'current_company_approval' => $this->current_company_approval,
            'current_company_approval_text' => $this->current_company_approval_text,
            'new_company_approval' => $this->new_company_approval,
            'new_company_approval_text' => $this->new_company_approval_text,

            'added_by' => $this->added_by,
            'created_at' => $this->created_at,

            // معلومات الموظف المنقول
            'employee_name' => $this->when(
                $this->relationLoaded('employee'),
                fn() => $this->employee ? ($this->employee->first_name . ' ' . $this->employee->last_name) : 'غير محدد'
            ),

            // معلومات الموظف إذا كانت محملة
            'employee' => $this->when($this->relationLoaded('employee'), function () {
                if (!$this->employee) return null;

                $firstName = $this->employee->first_name ?? '';
                $lastName = $this->employee->last_name ?? '';
                $fullName = trim($firstName . ' ' . $lastName);

                return [
                    'user_id' => $this->employee->user_id,
                    'first_name' => $firstName ?: null,
                    'last_name' => $lastName ?: null,
                    'email' => $this->employee->email,
                    'full_name' => $fullName ?: 'غير محدد',
                ];
            }),

            // معلومات القسم القديم
            'old_department_name' => $this->when(
                $this->relationLoaded('oldDepartment'),
                fn() => $this->oldDepartment?->department_name ?? 'غير محدد'
            ),

            // معلومات القسم الجديد
            'new_department_name' => $this->when(
                $this->relationLoaded('newDepartment'),
                fn() => $this->newDepartment?->department_name ?? 'غير محدد'
            ),

            // معلومات المسمى الوظيفي القديم
            'old_designation_name' => $this->when(
                $this->relationLoaded('oldDesignation'),
                fn() => $this->oldDesignation?->designation_name ?? 'غير محدد'
            ),

            // معلومات المسمى الوظيفي الجديد
            'new_designation_name' => $this->when(
                $this->relationLoaded('newDesignation'),
                fn() => $this->newDesignation?->designation_name ?? 'غير محدد'
            ),

            // معلومات من أضاف الطلب
            'added_by_name' => $this->when(
                $this->relationLoaded('addedBy'),
                fn() => $this->addedBy ? ($this->addedBy->first_name . ' ' . $this->addedBy->last_name) : 'غير محدد'
            ),
        ];
    }
}
