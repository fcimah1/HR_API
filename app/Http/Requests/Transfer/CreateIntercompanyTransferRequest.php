<?php

namespace App\Http\Requests\Transfer;

use App\Enums\TransferTypeEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class CreateIntercompanyTransferRequest extends FormRequest
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

            // النقل بين الشركات
            'new_company_id' => 'required|integer|exists:ci_erp_users,user_id',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'الموظف مطلوب',
            'employee_id.exists' => 'الموظف غير موجود',
            'transfer_date.required' => 'تاريخ النقل مطلوب',
            'transfer_date.date' => 'تنسيق تاريخ النقل غير صحيح',
            'reason.required' => 'سبب النقل مطلوب',
            'notify_send_to.array' => 'حقل الإشعار يجب أن يكون مصفوفة',
            'notify_send_to.exists' => 'أحد المستلمين غير موجود',
            'new_company_id.required' => 'الشركة الجديدة مطلوبة',
            'new_company_id.exists' => 'الشركة الجديدة غير موجودة',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = Auth::user();
            $employeeId = $this->input('employee_id');
            $newCompanyId = $this->input('new_company_id');

            $effectiveCompanyId = $user->user_type === 'company'
                ? $user->user_id
                : $user->company_id;

            // التحقق من أن الشركة الجديدة مختلفة عن الشركة الحالية
            if ($newCompanyId && $newCompanyId == $effectiveCompanyId) {
                $validator->errors()->add('new_company_id', 'الشركة الجديدة يجب أن تكون مختلفة عن الشركة الحالية');
            }

            // التحقق من أن الشركة الجديدة من نوع company
            if ($newCompanyId) {
                $company = \App\Models\User::find($newCompanyId);
                if ($company && $company->user_type !== 'company') {
                    $validator->errors()->add('new_company_id', 'المعرف المحدد ليس شركة');
                }
            }
        });
    }

    /**
     * Get the transfer type for this request.
     */
    public function getTransferType(): string
    {
        return TransferTypeEnum::INTERCOMPANY->value;
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
