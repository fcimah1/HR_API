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
 *     schema="StoreAccountRequest",
 *     title="StoreAccountRequest",
 *     description="طلب إنشاء حساب مالي جديد",
 *     required={"account_name"},
 *     @OA\Property(property="account_name", type="string", example="حساب البنك الأهلي", description="اسم الحساب"),
 *     @OA\Property(property="account_opening_balance", type="number", format="float", example=10000.00, description="الرصيد الافتتاحي"),
 *     @OA\Property(property="account_number", type="string", example="SA1234567890", description="رقم الحساب البنكي"),
 *     @OA\Property(property="branch_code", type="string", example="001", description="رمز الفرع"),
 *     @OA\Property(property="bank_branch", type="string", example="فرع الرياض", description="فرع البنك")
 * )
 */
class StoreAccountRequest extends FormRequest
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
            'account_name' => ['required', 'string', 'max:255',
                Rule::unique('ci_finance_accounts', 'account_name')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'account_opening_balance' => ['nullable', 'numeric', 'min:0'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'branch_code' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'account_name' => 'اسم الحساب',
            'account_opening_balance' => 'الرصيد الافتتاحي',
            'account_number' => 'رقم الحساب',
            'branch_code' => 'رمز الفرع',
            'bank_branch' => 'فرع البنك',
        ];
    }

    public function messages(): array
    {
        return [
            'account_name.required' => 'اسم الحساب مطلوب',
            'account_name.max' => 'اسم الحساب يجب أن لا يتجاوز 255 حرفاً',
            'account_name.unique' => 'اسم الحساب موجود بالفعل',
            'account_opening_balance.numeric' => 'الرصيد الافتتاحي يجب أن يكون رقماً',
            'account_opening_balance.min' => 'الرصيد الافتتاحي لا يمكن أن يكون سالباً',
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
