<?php

namespace App\Http\Requests\Transfer;

use App\Enums\TransferTypeEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class CreateInternalTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            // فحص الموظف - لا يمكن طلب نقل لموظف أعلى في المستوى
            'employee_id' => [
                'required',
                'integer',
                'exists:ci_erp_users,user_id',
                new \App\Rules\CanRequestForEmployee(),
            ],
            'transfer_date' => 'required|date|after_or_equal:today',
            'reason' => 'required|string',
            'notify_send_to' => ['nullable', 'array', 'exists:ci_erp_users,user_id', new \App\Rules\CanNotifyUser()],
            // النقل الداخلي
            'transfer_department' => 'required|integer|exists:ci_departments,department_id',
            'transfer_designation' => 'required|integer|exists:ci_designations,designation_id',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'الموظف مطلوب',
            'employee_id.exists' => 'الموظف غير موجود',
            'transfer_date.required' => 'تاريخ النقل مطلوب',
            'transfer_date.date' => 'تنسيق تاريخ النقل غير صحيح',
            'transfer_date.after_or_equal' => 'تاريخ النقل يجب أن يكون تاريخاً متأخرًا أو يساوي اليوم',
            'reason.required' => 'سبب النقل مطلوب',
            'notify_send_to.array' => 'حقل الإشعار يجب أن يكون مصفوفة',
            'notify_send_to.exists' => 'أحد المستلمين غير موجود',
            'transfer_department.required' => 'القسم الجديد مطلوب',
            'transfer_department.exists' => 'القسم غير موجود',
            'transfer_designation.required' => 'المسمى الوظيفي الجديد مطلوب',
            'transfer_designation.exists' => 'المسمى الوظيفي غير موجود',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = Auth::user();
            $employeeId = $this->input('employee_id');
            $transferDepartment = $this->input('transfer_department');
            $transferDesignation = $this->input('transfer_designation');

            $effectiveCompanyId = $user->user_type === 'company'
                ? $user->user_id
                : $user->company_id;

            // التحقق من أن القسم ينتمي للشركة
            if ($transferDepartment) {
                $department = \App\Models\Department::find($transferDepartment);
                if ($department && $department->company_id != $effectiveCompanyId) {
                    $validator->errors()->add('transfer_department', 'القسم المحدد لا ينتمي إلى الشركة');
                }
            }

            // التحقق من أن المسمى ينتمي للشركة
            if ($transferDesignation) {
                $designation = \App\Models\Designation::find($transferDesignation);
                if ($designation && $designation->company_id != $effectiveCompanyId) {
                    $validator->errors()->add('transfer_designation', 'المسمى المحدد لا ينتمي إلى الشركة');
                }
            }
        });
    }

    /**
     * Get the transfer type for this request.
     */
    public function getTransferType(): string
    {
        return TransferTypeEnum::INTERNAL->value;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422));
    }
}
