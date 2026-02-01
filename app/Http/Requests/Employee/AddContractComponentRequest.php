<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Services\SimplePermissionService;

/**
 * @OA\Schema(
 *     schema="AddContractComponentRequest",
 *     title="Add Contract Component Request",
 *     required={"pay_title", "pay_amount"},
 *     @OA\Property(property="pay_title", type="string", example="Transport Allowance"),
 *     @OA\Property(property="pay_amount", type="number", example=500)
 * )
 */
class AddContractComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (new SimplePermissionService())->getEffectiveCompanyId(Auth::user());

        return [
            'pay_title' => [
                'required',
                'string',
                'max:255',
                Rule::exists('ci_contract_options', 'option_title')->where(function ($query) use ($companyId) {
                    $query->where('salay_type', 'allowances');
                    if ($companyId !== 0) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
            'pay_amount' => 'required|numeric|min:0',
            'is_taxable' => 'nullable|integer|in:0,1',
            'is_fixed' => 'nullable|integer|in:1,2',
            'contract_option_id' => ['nullable', 'integer', Rule::exists('ci_contract_options', 'id')->where(function ($query) use ($companyId) {
                $query->where('salay_type', 'allowances');
                if ($companyId !== 0) {
                    $query->where('company_id', $companyId);
                }
            })],
        ];
    }

    public function messages(): array
    {
        return [
            'pay_title.required' => 'العنوان مطلوب',
            'pay_amount.required' => 'المبلغ مطلوب',
            'pay_title.string' => 'العنوان يجب أن يكون نصاً',
            'pay_title.max' => 'العنوان يجب أن لا يتجاوز 255 حرفاً',
            'pay_title.exists' => 'العنوان غير موجود',
            'pay_amount.numeric' => 'المبلغ يجب أن يكون رقماً',
            'pay_amount.min' => 'المبلغ يجب أن يكون أكبر من أو يساوي 0',
            'is_taxable.in' => 'قيمة الخضوع للضريبة غير صحيحة',
            'is_fixed.in' => 'نوع القيمة (ثابتة/نسبة) غير صحيح',
            'contract_option_id.integer' => 'معرف الخيار يجب أن يكون رقماً',
            'contract_option_id.exists' => 'القيمة غير موجودة',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'بيانات غير صالحة',
            'errors' => $validator->errors()->all()
        ], 422));
    }
}
