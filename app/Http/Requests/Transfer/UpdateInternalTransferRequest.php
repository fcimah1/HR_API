<?php

namespace App\Http\Requests\Transfer;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class UpdateInternalTransferRequest extends FormRequest
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
            'new_salary' => 'required|numeric|min:0',
            'new_currency' => 'required|integer|exists:ci_currencies,currency_id',
        ];
    }

    public function messages(): array
    {
        return [
            'transfer_date.date' => 'تنسيق تاريخ النقل غير صحيح',
            'reason.required' => 'سبب النقل مطلوب',
            'notify_send_to.array' => 'حقل الإشعار يجب أن يكون مصفوفة',
            'notify_send_to.exists' => 'أحد المستلمين غير موجود',
            'transfer_department.exists' => 'القسم غير موجود',
            'transfer_designation.exists' => 'المسمى الوظيفي غير موجود',
            'new_salary.numeric' => 'الراتب يجب أن يكون رقماً',
            'new_currency.exists' => 'العملة غير موجودة',
            'transfer_department.required' => 'القسم مطلوب',
            'transfer_designation.required' => 'المسمى الوظيفي مطلوب',
            'new_salary.required' => 'الراتب مطلوب',
            'new_currency.required' => 'العملة مطلوبة',
        ];
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
