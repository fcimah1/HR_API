<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="StoreDepositRequest",
 *     title="StoreDepositRequest",
 *     description="طلب تسجيل إيداع جديد",
 *     required={"account_id", "amount"},
 *     @OA\Property(property="account_id", type="integer", example=1, description="معرف الحساب المالي"),
 *     @OA\Property(property="amount", type="number", format="float", example=5000.00, description="مبلغ الإيداع"),
 *     @OA\Property(property="transaction_date", type="string", format="date", example="2026-01-15", description="تاريخ المعاملة"),
 *     @OA\Property(property="entity_category_id", type="integer", example=3, description="فئة الإيداع"),
 *     @OA\Property(property="entity_id", type="integer", example=0, description="معرف الجهة"),
 *     @OA\Property(property="entity_type", type="string", example="client", description="نوع الجهة"),
 *     @OA\Property(property="payment_method_id", type="integer", example=1, description="طريقة الدفع"),
 *     @OA\Property(property="reference", type="string", example="INV-2026-001", description="رقم المرجع"),
 *     @OA\Property(property="description", type="string", example="إيداع نقدي من العميل", description="الوصف"),
 *     @OA\Property(property="attachment", type="string", format="binary", description="المرفق (صورة أو ملف)")
 * )
 */
class StoreDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionService = resolve(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('ci_finance_accounts', 'account_id')
                    ->where('company_id', $effectiveCompanyId),
            ],
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'nullable|date',
            'entity_category_id' => [
                'nullable',
                'integer',
                Rule::exists('ci_erp_constants', 'constants_id')
                    ->where('type', 'income_type')
                    ->where('company_id', $effectiveCompanyId),
            ],
            'entity_id' => 'nullable|integer',
            'entity_type' => 'nullable|string|max:100',
            'payment_method_id' => [
                'nullable',
                'integer',
                Rule::exists('ci_erp_constants', 'constants_id')
                    ->where('type', 'payment_method'),
            ],
            'reference' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
        ];
    }

    public function attributes(): array
    {
        return [
            'account_id' => 'الحساب',
            'amount' => 'المبلغ',
            'transaction_date' => 'تاريخ المعاملة',
            'entity_category_id' => 'فئة الإيداع',
            'entity_id' => 'الجهة',
            'entity_type' => 'نوع الجهة',
            'payment_method_id' => 'طريقة الدفع',
            'reference' => 'المرجع',
            'description' => 'الوصف',
            'attachment' => 'المرفق',
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'الحساب مطلوب',
            'account_id.exists' => 'الحساب غير موجود أو لا ينتمي لهذه الشركة',
            'amount.required' => 'المبلغ مطلوب',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'transaction_date.date' => 'تاريخ المعاملة غير صحيح',
            'entity_category_id.exists' => 'فئة الإيداع غير موجودة',
            'payment_method_id.exists' => 'طريقة الدفع غير موجودة',
            'attachment.mimes' => 'صيغة المرفق غير مدعومة',
            'attachment.max' => 'حجم المرفق يتجاوز الحد المسموح (10MB)',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
