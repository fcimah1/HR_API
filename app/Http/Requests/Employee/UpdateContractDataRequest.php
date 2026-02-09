<?php

namespace App\Http\Requests\Employee;

use App\Services\SimplePermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     schema="UpdateContractDataRequest",
 *     title="Update Contract Data Request",
 *     required={},
 *     @OA\Property(property="branch_id", type="integer", example=1),
 *     @OA\Property(property="default_language", type="string", example="en"),
 *     @OA\Property(property="date_of_joining", type="string", format="date", example="2022-01-01"),
 *     @OA\Property(property="department_id", type="integer", example=1),
 *     @OA\Property(property="designation_id", type="integer", example=1),
 *     @OA\Property(property="basic_salary", type="number", example=1000),
 *     @OA\Property(property="currency_id", type="integer", example=1),
 *     @OA\Property(property="hourly_rate", type="number", example=10),
 *     @OA\Property(property="salary_payment_method", type="string", example="DEPOSIT"),
 *     @OA\Property(property="salary_type", type="integer", example=1),
 *     @OA\Property(property="office_shift_id", type="integer", example=1),
 *     @OA\Property(property="contract_end", type="integer", example=1),
 *     @OA\Property(property="date_of_leaving", type="string", format="date", example="2022-12-31"),
 *     @OA\Property(property="reporting_manager", type="integer", example=1),
 *     @OA\Property(property="job_type", type="integer", example=1),
 *     @OA\Property(property="is_work_from_home", type="integer", example=0),
 *     @OA\Property(property="not_part_of_orgchart", type="integer", example=0),
 *     @OA\Property(property="not_part_of_system_reports", type="integer", example=0),
 *     @OA\Property(property="role_description", type="string", example="Description")
 * )
 */


class UpdateContractDataRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = resolve(SimplePermissionService::class)->getEffectiveCompanyId(Auth::user());


        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('ci_branchs', 'branch_id')->where(function ($query) use ($companyId) {
                    if ($companyId !== 0) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
            'default_language' => 'required|string|max:5',
            'date_of_joining' => 'required|date',
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
            'basic_salary' => 'required|numeric|min:0',
            'currency_id' => 'required|integer|exists:ci_currencies,currency_id',
            'hourly_rate' => 'nullable|numeric|min:0',
            'salary_payment_method' => 'required|string|in:DEPOSIT,CASH',
            'salary_type' => 'required|integer|in:1,2',
            'office_shift_id' => [
                'required',
                'integer',
                Rule::exists('ci_office_shifts', 'office_shift_id')->where(function ($query) use ($companyId) {
                    if ($companyId !== 0) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
            'contract_end' => 'nullable|integer',
            'date_of_leaving' => 'nullable|date',
            'reporting_manager' => [
                'nullable',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($companyId) {
                    if ($companyId !== 0) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
            'job_type' => 'nullable|integer|in:0,1,2,3',
            'is_work_from_home' => 'nullable|integer|in:0,1',
            'not_part_of_orgchart' => 'nullable|integer|in:0,1',
            'not_part_of_system_reports' => 'nullable|integer|in:0,1',
            'role_description' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'فرع العمل مطلوب',
            'default_language.required' => 'اللغة الافتراضية مطلوبة',
            'date_of_joining.required' => 'تاريخ الالتحاق مطلوب',
            'department_id.required' => 'القسم مطلوب',
            'designation_id.required' => 'المسمى الوظيفي مطلوب',
            'basic_salary.required' => 'الراتب الأساسي مطلوب',
            'currency_id.required' => 'العملة مطلوبة',
            'salary_payment_method.required' => 'طريقة دفع الراتب مطلوبة',
            'salary_type.required' => 'نوع الراتب مطلوب',
            'office_shift_id.required' => 'الوردية مطلوبة',
            'role_description.required' => 'وصف الوظيفة مطلوب',
            'basic_salary.numeric' => 'الراتب الأساسي يجب أن يكون رقماً',
            'hourly_rate.numeric' => 'أجر الساعة يجب أن يكون رقماً',
            'date_of_joining.date' => 'تاريخ الالتحاق غير صحيح',
            'salary_type.in' => 'نوع الراتب غير صحيح',
            'salary_payment_method.in' => 'طريقة دفع الراتب غير صحيح',
            'is_work_from_home.in' => 'العمل من المنزل غير صحيح',
            'not_part_of_orgchart.in' => 'جزء من الهيكل التنظيمي غير صحيح',
            'not_part_of_system_reports.in' => 'جزء من تقارير النظام غير صحيح',
            'job_type.in' => 'نوع العمل غير صحيح',
            'job_type.integer' => 'نوع العمل يجب أن يكون رقماً (0-3)',
            'reporting_manager.integer' => 'المدير المباشر غير صحيح',
            'department_id.integer' => 'القسم غير صحيح',
            'designation_id.integer' => 'المسمى الوظيفي غير صحيح',
            'office_shift_id.integer' => 'الوردية غير صحيح',
            'contract_end.integer' => 'نهاية العقد غير صحيح',
            'date_of_leaving.date' => 'تاريخ المغادرة غير صحيح',
            'basic_salary.min' => 'الراتب الأساسي يجب أن يكون أكبر من 0',
            'hourly_rate.min' => 'أجر الساعة يجب أن يكون أكبر من 0',
            'department_id.exists' => 'هذا القسم غير صحيح',
            'designation_id.exists' => 'هذا المسمى الوظيفي غير صحيح',
            'currency_id.exists' => 'هذه العملة غير صحيحة',
            'office_shift_id.exists' => 'هذه الوردية غير صحيحة',
            'reporting_manager.exists' => 'هذا المدير المباشر غير صحيح',
            'branch_id.exists' => 'هذا الفرع غير صحيح',

        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        $formattedErrors = [];
        foreach ($errors->all() as $error) {
            $formattedErrors[] = $error;
        }
        Log::error('Validation failed', [
            'errors' => $formattedErrors
        ]);
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => ' فشل التحقق من البيانات ',
            'errors' => $formattedErrors
        ], 422));
    }
}
