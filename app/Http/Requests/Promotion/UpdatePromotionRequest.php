<?php

namespace App\Http\Requests\Promotion;

use App\Enums\NumericalStatusEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
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
            'promotion_title' => 'nullable|string|max:255',
            'promotion_date' => 'nullable|date',
            'new_designation_id' => [
                'nullable',
                'integer',
                Rule::exists('ci_designations', 'designation_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'new_department_id' => [
                'nullable',
                'integer',
                Rule::exists('ci_departments', 'department_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'new_salary' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'notify_send_to' => 'nullable|array',
            'notify_send_to.*' => [
                'integer',
                Rule::notIn([$this->employee_id]),
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(array_map(fn($case) => ucfirst(strtolower($case->name)), NumericalStatusEnum::cases()))
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'promotion_title.required' => 'العنوان مطلوب',
            'promotion_date.required' => 'التاريخ مطلوب',
            'new_designation_id.required' => 'المسمى الوظيفي الجديد مطلوب',
            'new_designation_id.exists' => 'المسمى الوظيفي الجديد غير صالح',
            'new_department_id.required' => 'القسم الجديد مطلوب',
            'new_department_id.exists' => 'القسم الجديد غير صالح',
            'new_salary.required' => 'الراتب الجديد مطلوب',
            'new_salary.min' => 'الراتب الجديد يجب أن يكون أكبر من 0',
            'description.required' => 'الوصف مطلوب',
            'notify_send_to.required' => 'يجب تحديد من يحصل على إشعار الترقية',
            'notify_send_to.*.required' => 'يجب تحديد من يحصل على إشعار الترقية',
            'notify_send_to.*.exists' => 'يجب تحديد من يحصل على إشعار الترقية',
            'notify_send_to.*.not_in' => 'يجب تحديد من يحصل على إشعار الترقية',
            'status.required' => 'الحالة مطلوبة',
            'status.string' => 'الحالة يجب ان تكون نص',
            'status.in' => 'الحالة غير صالحة يجب ان تكون بين [Pending,Approved,Rejected]',
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
