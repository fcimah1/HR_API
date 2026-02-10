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
 *     schema="StoreEmployeeAccountRequest",
 *     title="StoreEmployeeAccountRequest",
 *     description="طلب إنشاء حساب جديد",
 *     required={"account_name"},
 *     @OA\Property(property="account_name", type="string", example="بنك الوفا", description="اسم الحساب")
 * )
 */
class StoreEmployeeAccountRequest extends FormRequest
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
            'account_name' => [
                'required',
                'string',
                'max:200',
                Rule::unique('ci_employee_accounts', 'account_name')
                    ->where('company_id', $effectiveCompanyId)
                    ->ignore($this->route('id'), 'account_id')
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'account_name' => 'اسم الحساب',
        ];
    }

    public function messages(): array
    {
        return [
            'account_name.required' => 'اسم حساب الموظف مطلوب',
            'account_name.max' => 'اسم الحساب يجب أن لا يتجاوز 200 حرف',
            'account_name.unique' => 'اسم الحساب موجود بالفعل',
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
