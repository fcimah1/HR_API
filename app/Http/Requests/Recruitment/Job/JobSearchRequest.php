<?php

namespace App\Http\Requests\Recruitment\Job;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class JobSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'paginate' => $this->has('paginate') ? filter_var($this->paginate, FILTER_VALIDATE_BOOLEAN) : true,
            'per_page' => $this->per_page ?? 10,
        ]);
    }

    public function rules(): array
    {
        $permissionService = resolve(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'job_title' => 'sometimes|string|max:255',
            'designation_id' => [
                'sometimes',
                'integer',
                Rule::exists('ci_designations', 'designation_id')
                    ->where('company_id', $effectiveCompanyId)
            ],
            'job_type' => 'sometimes|string',
            'search' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'paginate' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'job_title.string' => 'العنوان يجب أن يكون نصاً',
            'job_title.max' => 'العنوان يجب أن يكون أقل من 255 حرف',
            'designation_id.required' => 'المسمى الوظيفى مطلوب',
            'designation_id.integer' => 'المسمى الوظيفى يجب أن يكون رقماً',
            'designation_id.exists' => 'المسمى الوظيفى غير موجود',
            'job_type.string' => 'النوع يجب أن يكون نصاً',
            'search.string' => 'البحث يجب أن يكون نصاً',
            'per_page.integer' => 'عدد النتائج يجب أن يكون رقماً',
            'per_page.min' => 'عدد النتائج يجب أن يكون 1 على الأقل',
            'page.integer' => 'رقم الصفحة يجب أن يكون رقماً',
            'paginate.boolean' => 'قيمة الترقيم غير صحيحة',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل فى التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422));
    }
}
