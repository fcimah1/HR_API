<?php

namespace App\Http\Requests\Transfer;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class UpdateIntercompanyTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    protected function prepareForValidation()
    {
        $transferId = $this->route('id');
        $transfer = \App\Models\Transfer::find($transferId);

        if (!$transfer) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود',
                'errors' => []
            ], 404));
        }

        // Check company ownership
        $user = Auth::user();
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId($user);

        if ($transfer->company_id !== $effectiveCompanyId) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود',
                'errors' => []
            ], 404));
        }

        if ($transfer->transfer_type !== \App\Enums\TransferTypeEnum::INTERCOMPANY->value) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'فشل التحقق من البيانات',
                'errors' => [
                    'transfer_id' => ["لا يمكنك تعديل طلب من نوع ({$transfer->transfer_type_text}) عبر هذا المسار."]
                ]
            ], 422));
        }
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
            'transfer_date.date' => 'تنسيق تاريخ النقل غير صحيح',
            'notify_send_to.array' => 'حقل الإشعار يجب أن يكون مصفوفة',
            'notify_send_to.exists' => 'أحد المستلمين غير موجود',
            'new_company_id.exists' => 'الشركة الجديدة غير موجودة',
            'new_company_id.required' => 'الشركة الجديدة مطلوبة',
            'reason.required' => 'سبب النقل مطلوب',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = Auth::user();
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

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422));
    }
}
