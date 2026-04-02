<?php

declare(strict_types=1);

namespace App\Http\Requests\EndOfService;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="CalculateEndOfServiceRequest",
 *     title="CalculateEndOfServiceRequest",
 *     description="طلب حساب مستحقات نهاية الخدمة",
 *     required={"employee_id", "termination_date", "termination_type"},
 *     @OA\Property(property="employee_id", type="integer", example=1),
 *     @OA\Property(property="termination_date", type="string", format="date", example="2026-02-09"),
 *     @OA\Property(property="termination_type", type="string", enum={"resignation", "termination", "end_of_contract"}, example="resignation"),
 *     @OA\Property(property="include_leave", type="boolean", example=true),
 * )
 */
class CalculateEndOfServiceRequest extends FormRequest
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
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')
                    ->where('company_id', $effectiveCompanyId)
            ],
            'termination_date' => 'required|date|date_format:Y-m-d|after_or_equal:today',
            'termination_type' => 'required|string|in:resignation,termination,end_of_contract',
            'include_leave' => 'required|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'employee_id' => 'الموظف',
            'termination_date' => 'تاريخ انتهاء الخدمة',
            'termination_type' => 'نوع انتهاء الخدمة',
            'include_leave' => 'تضمين الإجازات',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'الموظف مطلوب',
            'employee_id.integer' => 'الموظف يجب أن يكون رقماً',
            'employee_id.exists' => 'الموظف غير موجود أو لا يتبع لشركتك',
            'termination_date.required' => 'تاريخ انتهاء الخدمة مطلوب',
            'termination_date.date' => 'تاريخ انتهاء الخدمة غير صحيح',
            'termination_date.date_format' => 'تاريخ انتهاء الخدمة يجب أن يكون بصيغة Y-m-d',
            'termination_date.after_or_equal' => 'تاريخ انتهاء الخدمة يجب أن يكون اليوم أو بعده',
            'termination_type.required' => 'نوع انتهاء الخدمة مطلوب',
            'termination_type.in' => 'نوع انتهاء الخدمة يجب أن يكون استقالة أو إنهاء خدمة أو انتهاء عقد',
            'include_leave.required' => 'تضمين الإجازات مطلوب',
            'include_leave.boolean' => 'تضمين الإجازات يجب أن يكون صحيح أو خطأ',
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
