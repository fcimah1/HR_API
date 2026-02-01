<?php

namespace App\Http\Requests\Employee;

use App\Services\SimplePermissionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends BaseEmployeeRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $companyId = new SimplePermissionService()->getEffectiveCompanyId(Auth::user());
        $employeeId = $this->route('id');

        $rules = [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('ci_erp_users', 'email')->ignore($employeeId, 'user_id')],
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('ci_erp_users', 'username')->ignore($employeeId, 'user_id')],
            'password' => ['sometimes', 'string', 'min:6'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['M', 'F'])],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('ci_departments', 'department_id')->where(function ($query) use ($companyId) {
                    if ($companyId !== 0) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
            'designation_id' => [
                'required',
                'integer',
                Rule::exists('ci_designations', 'designation_id')->where(function ($query) use ($companyId) {
                    if ($companyId !== 0) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'date_of_joining' => ['nullable', 'date'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'address_1' => ['nullable', 'string', 'max:500'],
            'address_2' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'experience' => ['nullable', 'string', 'max:1000'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'salary_type' => ['nullable', 'string', 'max:50'],
            'salary_payment_method' => ['nullable', 'string', 'max:50'],
            'currency_id' => 'required|integer|exists:ci_currencies,currency_id',
            'role_description' => ['nullable', 'string', 'max:1000'],
            'contract_end' => ['nullable', 'date', 'after:today'],
            'date_of_leaving' => ['nullable', 'date'],
            'religion_id' => ['nullable', 'integer'],
            'citizenship_id' => ['nullable', 'integer'],
            'fb_profile' => ['nullable', 'url', 'max:255'],
            'twitter_profile' => ['nullable', 'url', 'max:255'],
            'gplus_profile' => ['nullable', 'url', 'max:255'],
            'linkedin_profile' => ['nullable', 'url', 'max:255'],
            'account_title' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'contact_full_name' => ['nullable', 'string', 'max:255'],
            'contact_phone_no' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'employee_idnum' => ['nullable', 'string', 'max:50'],
            'passport_no' => ['nullable', 'string', 'max:50'],
            'passport_date' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
            'biotime_id' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'الاسم الأول مطلوب',
            'last_name.required' => 'الاسم الأخير مطلوب',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'username.required' => 'اسم المستخدم مطلوب',
            'username.unique' => 'اسم المستخدم مستخدم بالفعل',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'department_id.required' => 'القسم مطلوب',
            'department_id.exists' => 'القسم المحدد غير موجود',
            'designation_id.required' => 'المسمى الوظيفي مطلوب',
            'designation_id.exists' => 'المسمى الوظيفي المحدد غير موجود',
            'gender.required' => 'الجنس مطلوب',
            'gender.in' => 'الجنس يجب أن يكون ذكر أو أنثى',
            'basic_salary.required' => 'الراتب الأساسي مطلوب',
            'basic_salary.numeric' => 'الراتب الأساسي يجب أن يكون رقم',
            'basic_salary.min' => 'الراتب الأساسي لا يمكن أن يكون سالب',
            'date_of_joining.required' => 'تاريخ التوظيف مطلوب',
            'date_of_joining.date' => 'تاريخ التوظيف غير صحيح',
            'date_of_birth.required' => 'تاريخ الميلاد مطلوب',
            'date_of_birth.date' => 'تاريخ الميلاد غير صحيح',
            'date_of_birth.before' => 'تاريخ الميلاد يجب أن يكون قبل اليوم',
            'contract_end.required' => 'تاريخ انتهاء العقد مطلوب',
            'contract_end.after' => 'تاريخ انتهاء العقد يجب أن يكون في المستقبل',
            'fb_profile.required' => 'رابط فيسبوك مطلوب',
            'fb_profile.url' => 'رابط فيسبوك غير صحيح',
            'twitter_profile.required' => 'رابط تويتر مطلوب',
            'twitter_profile.url' => 'رابط تويتر غير صحيح',
            'gplus_profile.required' => 'رابط جوجل بلس مطلوب',
            'gplus_profile.url' => 'رابط جوجل بلس غير صحيح',
            'linkedin_profile.required' => 'رابط لينكد إن مطلوب',
            'linkedin_profile.url' => 'رابط لينكد إن غير صحيح',
            'contact_email.required' => 'بريد جهة الاتصال مطلوب',
            'contact_email.email' => 'بريد جهة الاتصال غير صحيح',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'الاسم الأول',
            'last_name' => 'الاسم الأخير',
            'email' => 'البريد الإلكتروني',
            'username' => 'اسم المستخدم',
            'password' => 'كلمة المرور',
            'contact_number' => 'رقم الهاتف',
            'gender' => 'الجنس',
            'department_id' => 'القسم',
            'designation_id' => 'المسمى الوظيفي',
            'basic_salary' => 'الراتب الأساسي',
            'date_of_joining' => 'تاريخ التوظيف',
            'date_of_birth' => 'تاريخ الميلاد',
            'is_active' => 'حالة التفعيل',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
