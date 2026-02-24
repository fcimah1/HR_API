<?php

declare(strict_types=1);

namespace App\Http\Requests\Payslip;

use App\Enums\JobTypeEnum;
use App\Enums\PaymentMethodEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayslipAjaxListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'staff_id' => ['nullable', 'integer'],
            'salary_payment_method' => ['nullable', 'string', Rule::in(array_map(fn($c) => $c->value, PaymentMethodEnum::cases()))],
            'job_type' => ['nullable', 'integer', Rule::in(array_map(fn($c) => $c->value, JobTypeEnum::cases()))],
            'branch_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_date.regex' => 'صيغة الشهر غير صحيحة (YYYY-MM)',
            'salary_payment_method.in' => 'طريقة الدفع غير صحيحة',
            'job_type.in' => 'نوع الوظيفة غير صحيح',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException(
            $validator,
            response()->json([
                'message' => 'فشل التحقق من البيانات',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
