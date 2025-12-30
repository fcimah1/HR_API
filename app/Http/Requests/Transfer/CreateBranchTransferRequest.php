<?php

namespace App\Http\Requests\Transfer;

use App\Enums\TransferTypeEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class CreateBranchTransferRequest extends FormRequest
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
            // النقل بين الفروع
            'new_branch_id' => 'required|integer|exists:ci_branchs,branch_id',
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
            'new_branch_id.required' => 'الفرع الجديد مطلوب',
            'new_branch_id.exists' => 'الفرع الجديد غير موجود',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = Auth::user();
            $employeeId = $this->input('employee_id');
            $newBranchId = $this->input('new_branch_id');

            $effectiveCompanyId = $user->user_type === 'company'
                ? $user->user_id
                : $user->company_id;

            // التحقق من أن الفرع ينتمي للشركة
            if ($newBranchId) {
                $branch = \App\Models\Branch::find($newBranchId);
                if ($branch && $branch->company_id != $effectiveCompanyId) {
                    $validator->errors()->add('new_branch_id', 'الفرع المحدد لا ينتمي إلى الشركة');
                }
            }
        });
    }

    /**
     * Get the transfer type for this request.
     */
    public function getTransferType(): string
    {
        return TransferTypeEnum::BRANCH->value;
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
