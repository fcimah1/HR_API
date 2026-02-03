<?php

namespace App\Http\Requests\Promotion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'promotion_title' => 'required|string|max:255',
            'promotion_date' => 'required|date',
            'new_designation_id' => [
                'required',
                'integer',
                Rule::exists('ci_designations', 'designation_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'new_department_id' => [
                'required',
                'integer',
                Rule::exists('ci_departments', 'department_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'new_salary' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'notify_send_to' => 'nullable|array',
            'notify_send_to.*' => [
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'اسم الموظف مطلوب',
            'employee_id.exists' => 'الموظف المختار غير صالح',
            'promotion_title.required' => 'عنوان الترقية مطلوب',
            'promotion_date.required' => 'تاريخ الترقية مطلوب',
            'new_designation_id.required' => 'المسمى الوظيفي الجديد مطلوب',
            'new_designation_id.exists' => 'المسمى الوظيفي الجديد غير صالح',
            'new_department_id.required' => 'القسم الجديد مطلوب',
            'new_department_id.exists' => 'القسم الجديد غير صالح',
            'new_salary.required' => 'الراتب الجديد مطلوب',
            'new_salary.min' => 'الراتب الجديد يجب أن يكون أكبر من 0',
            'notify_send_to.required' => 'يجب تحديد من يحصل على إشعار الترقية',
            'notify_send_to.*.required' => 'يجب تحديد من يحصل على إشعار الترقية',
            'notify_send_to.*.exists' => 'يجب تحديد من يحصل على إشعار الترقية',
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
