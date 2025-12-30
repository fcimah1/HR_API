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
            'transfer_type' => $this->transfer_type,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'old_salary' => $this->old_salary,
            'old_designation' => $this->old_designation,
            // معلومات المسمى الوظيفي القديم
            'old_designation_name' => ($this->old_designation && $this->old_designation > 0)
                ? ($this->oldDesignation?->designation_name ?? 'غير محدد')
                : 'غير محدد',
            'old_department' => $this->old_department,
            // معلومات القسم القديم
            'old_department_name' => ($this->old_department && $this->old_department > 0)
                ? ($this->oldDepartment?->department_name ?? 'غير محدد')
                : 'غير محدد',

            'transfer_date' => $this->transfer_date,
            'transfer_department' => $this->transfer_department,
            // معلومات القسم الجديد
            'new_department_name' => ($this->transfer_department && $this->transfer_department > 0)
                ? ($this->newDepartment?->department_name ?? 'غير محدد')
                : 'غير محدد',

            'transfer_designation' => $this->transfer_designation,
            // معلومات المسمى الوظيفي الجديد
            'new_designation_name' => ($this->transfer_designation && $this->transfer_designation > 0)
                ? ($this->newDesignation?->designation_name ?? 'غير محدد')
                : 'غير محدد',

            'new_salary' => $this->new_salary,
            'old_company_id' => $this->old_company_id,
            // معلومات الشركة القديمة
            'old_company_name' => $this->oldCompany?->company_name ?? 'غير محدد',

            'old_branch_id' => $this->old_branch_id,
            // معلومات الفرع القديم
            'old_branch_name' => ($this->old_branch_id && $this->old_branch_id > 0)
                ? ($this->oldBranch?->branch_name ?? 'غير محدد')
                : 'غير محدد',

            // معلومات الشركة الجديدة - تظهر فقط للنقل بين الشركات
            'new_company_id' => $this->when(
                $this->transfer_type === 'intercompany',
                fn() => $this->new_company_id
            ),
            'new_company_name' => $this->when(
                $this->transfer_type === 'intercompany',
                fn() => $this->newCompany?->company_name ?? 'غير محدد'
            ),
            // معلومات الفرع الجديد - تظهر فقط للنقل بين الفروع أو بين الشركات
            'new_branch_id' => $this->when(
                in_array($this->transfer_type, ['branch', 'intercompany']),
                fn() => $this->new_branch_id
            ),
            'new_branch_name' => $this->when(
                in_array($this->transfer_type, ['branch', 'intercompany']),
                fn() => ($this->new_branch_id && $this->new_branch_id > 0)
                    ? ($this->newBranch?->branch_name ?? 'غير محدد')
                    : 'غير محدد'
            ),

            'old_currency' => $this->old_currency,
            // معلومات العملة القديمة
            'old_currency_name' => $this->when(
                $this->old_currency && $this->old_currency > 0,
                fn() => $this->oldCurrency?->currency_name ?? 'غير محدد'
            ),
            'new_currency' => $this->new_currency,
            // معلومات العملة الجديدة
            'new_currency_name' => $this->when(
                $this->new_currency && $this->new_currency > 0,
                fn() => $this->newCurrency?->currency_name ?? 'غير محدد'
            ),
            'reason' => $this->reason,
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
                    'department' => $this->employee->user_details?->department?->name ?? null,
                    'position' => $this->employee->user_details?->designation?->name ?? null,
                ];
            }),

            // معلومات من أضاف الطلب
            'added_by_name' => $this->when(
                $this->relationLoaded('addedBy'),
                fn() => $this->addedBy ? ($this->addedBy->first_name . ' ' . $this->addedBy->last_name) : 'غير محدد'
            ),

            // معلومات الموافقات
            'approvals' => $this->when($this->relationLoaded('approvals'), function () {
                return $this->approvals->map(function ($approval) {
                    return [
                        'status' => $approval->status,
                        'approval_level' => $approval->approval_level ?? 1,
                        'updated_at' => $approval->updated_at,
                        'staff' => isset($approval->staff) ? [
                            'user_id' => $approval->staff->user_id,
                            'first_name' => $approval->staff->first_name,
                            'last_name' => $approval->staff->last_name,
                            'full_name' => $approval->staff->full_name,
                            'email' => $approval->staff->email,
                            'department' => $approval->staff->user_details?->department?->name ?? null,
                            'position' => $approval->staff->user_details?->designation?->name ?? null,
                        ] : null
                    ];
                });
            }),
        ];
    }
}
