<?php

namespace App\Http\Requests\Employee;

use App\Services\SimplePermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateBankInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = Auth::user();
        $permissionService = app(SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId($user);
        
        return [
            'account_number' => 'nullable|string|max:50',
            'bank_name' => [
                'nullable',
                'integer',
                Rule::exists('ci_employee_accounts', 'account_id')->where(function ($query) use ($effectiveCompanyId) {
                        return $query->where('company_id', $effectiveCompanyId);
                    }),
            ],            
            'iban' => 'nullable|string|max:34|regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{4}[0-9]{7}([A-Z0-9]?){0,16}$/',
            'bank_branch' => 'nullable|string|max:255'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'account_number.string' => 'رقم الحساب يجب أن يكون نص',
            'account_number.max' => 'رقم الحساب يجب أن يكون أقل من 50 حرف',
            'bank_name.exists' => 'اسم البنك غير موجود',
            'bank_name.integer' => 'اسم البنك غير موجود',
            'iban.string' => 'رقم الآيبان يجب أن يكون نص',
            'iban.max' => 'رقم الآيبان يجب أن يكون أقل من 34 حرف',
            'iban.regex' => 'رقم الآيبان غير صحيح',
            'bank_branch.string' => 'فرع البنك يجب أن يكون نص',
            'bank_branch.max' => 'فرع البنك يجب أن يكون أقل من 255 حرف'
        ];
    }

    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        Log::error('validation errors',[
            'success' => false,
            'status_code' => 422,
            'url' => url()->current(),
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ]);
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ], 422));
    }
}
