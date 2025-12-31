<?php

namespace App\Http\Requests\Transfer;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class UpdateBranchTransferRequest extends FormRequest
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

        if ($transfer->transfer_type !== \App\Enums\TransferTypeEnum::BRANCH->value) {
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
            'employee_id' => [
                'required',
                'integer',
                'exists:ci_erp_users,user_id',
                new \App\Rules\CanRequestForEmployee(),
            ],
            'transfer_date' => 'required|date|after_or_equal:today',
            'reason' => 'required|string',
            'notify_send_to' => ['nullable', 'array', 'exists:ci_erp_users,user_id', new \App\Rules\CanNotifyUser()],

            // النقل بين الفروع
            'new_branch_id' => 'required|integer|exists:ci_branchs,branch_id',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'الموظف مطلوب',
            'employee_id.exists' => 'الموظف غير موجود',
            'transfer_date.date' => 'تنسيق تاريخ النقل غير صحيح',
            'transfer_date.after_or_equal' => 'تاريخ النقل يجب أن يكون تاريخاً متأخرًا أو يساوي اليوم',
            'reason.required' => 'سبب النقل مطلوب',
            'notify_send_to.array' => 'حقل الإشعار يجب أن يكون مصفوفة',
            'notify_send_to.exists' => 'أحد المستلمين غير موجود',
            'new_branch_id.exists' => 'الفرع الجديد غير موجود',
            'new_branch_id.required' => 'الفرع الجديد مطلوب',
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
