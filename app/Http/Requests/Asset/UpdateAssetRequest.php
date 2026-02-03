<?php

namespace App\Http\Requests\Asset;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    protected function prepareForValidation()
    {
        if ($this->has('is_working')) {
            $this->merge([
                'is_working' => filter_var($this->is_working, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'name' => 'nullable|string|max:255',
            'assets_category_id' => 'nullable|integer|exists:ci_erp_constants,constants_id',
            'brand_id' => 'nullable|integer|exists:ci_erp_constants,constants_id',
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'company_asset_code' => 'nullable|string|max:100',
            'purchase_date' => 'nullable|date',
            'invoice_number' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:100',
            'warranty_end_date' => 'nullable|date|after_or_equal:purchase_date',
            'asset_note' => 'nullable|string',
            'is_working' => 'nullable|boolean',
            'asset_image' => 'nullable|image|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'assets_category_id.exists' => 'الفئة المختارة غير صالحة',
            'brand_id.exists' => 'العلامة التجارية المختارة غير صالحة',
            'employee_id.exists' => 'الموظف المختار غير صالح',
            'warranty_end_date.after_or_equal' => 'تاريخ انتهاء الضمان يجب أن يكون بعد أو يساوي تاريخ الشراء',
            'asset_image.image' => 'الملف يجب أن يكون صورة',
            'asset_image.max' => 'حجم الصورة لا يجب أن يتجاوز 5 ميجابايت',
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
