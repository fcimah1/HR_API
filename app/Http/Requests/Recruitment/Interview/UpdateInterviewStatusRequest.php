<?php

namespace App\Http\Requests\Recruitment\Interview;

use App\Enums\Recruitment\InterviewStatusEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateInterviewStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', Rule::enum(InterviewStatusEnum::class)],
            'interview_remarks' => 'sometimes|string|nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'حقل الحالة مطلوب',
            'status.integer' => 'حقل الحالة يجب أن يكون رقمًا',
            'status.enum' => 'حقل الحالة يجب أن يكون من القيم المتاحة',
            'interview_remarks.string' => 'حقل الملاحظات يجب أن يكون نصًا',
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'الحالة',
            'interview_remarks' => 'ملاحظات المقابلة',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('status')) {
            $input = trim((string)$this->status);

            // Normalize Arabic characters for flexible matching
            $normalize = function ($str) {
                return str_replace(
                    ['ة', 'أ', 'إ', 'آ', 'ى'],
                    ['ه', 'ا', 'ا', 'ا', 'ي'],
                    $str
                );
            };

            $statusMap = [
                'لم يبدا' => InterviewStatusEnum::not_started()->value,
                'not_started' => InterviewStatusEnum::not_started()->value,
                'مقابله ناجحه' => InterviewStatusEnum::successful()->value,
                'successful' => InterviewStatusEnum::successful()->value,
                'مرفوض' => InterviewStatusEnum::rejected()->value,
                'rejected' => InterviewStatusEnum::rejected()->value,
            ];

            $normalizedInput = $normalize($input);
            $mappedValue = $statusMap[$input] ?? $statusMap[$normalizedInput] ?? $this->status;

            $this->merge([
                'status' => $mappedValue,
            ]);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
