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
 *     schema="StoreTransferRequest",
 *     title="StoreTransferRequest",
 *     description="طلب تحويل بين حسابين",
 *     required={"account_id", "to_account_id", "amount"},
 *     @OA\Property(property="account_id", type="integer", example=1, description="الحساب المصدر"),
 *     @OA\Property(property="to_account_id", type="integer", example=2, description="الحساب الهدف"),
 *     @OA\Property(property="amount", type="number", format="float", example=3000.00, description="مبلغ التحويل"),
 *     @OA\Property(property="transaction_date", type="string", format="date", example="2026-01-15", description="تاريخ المعاملة"),
 *     @OA\Property(property="payment_method_id", type="integer", example=1, description="طريقة الدفع"),
 *     @OA\Property(property="reference", type="string", example="TRF-2026-001", description="رقم المرجع"),
 *     @OA\Property(property="description", type="string", example="تحويل بين الحسابات", description="الوصف"),
 *     @OA\Property(property="attachment", type="string", format="binary", description="المرفق (صورة أو ملف)")
 * )
 */
class StoreTransferRequest extends FormRequest
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
            'to_account_id' => [
                'required',
                'integer',
                'different:account_id',
                Rule::exists('ci_finance_accounts', 'account_id')
                    ->where('company_id', $effectiveCompanyId),
            ],
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'nullable|date',
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
            'account_id' => 'الحساب المصدر',
            'to_account_id' => 'الحساب الهدف',
            'amount' => 'المبلغ',
            'transaction_date' => 'تاريخ المعاملة',
            'payment_method_id' => 'طريقة الدفع',
            'reference' => 'المرجع',
            'description' => 'الوصف',
            'attachment' => 'المرفق',
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'الحساب المصدر مطلوب',
            'account_id.exists' => 'الحساب المصدر غير موجود أو لا ينتمي لهذه الشركة',
            'to_account_id.required' => 'الحساب الهدف مطلوب',
            'to_account_id.exists' => 'الحساب الهدف غير موجود أو لا ينتمي لهذه الشركة',
            'to_account_id.different' => 'لا يمكن التحويل لنفس الحساب',
            'amount.required' => 'المبلغ مطلوب',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'transaction_date.date' => 'تاريخ المعاملة غير صحيح',
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
