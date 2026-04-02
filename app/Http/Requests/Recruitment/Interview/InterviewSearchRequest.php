<?php

namespace App\Http\Requests\Recruitment\Interview;

use App\Enums\Recruitment\InterviewStatusEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class InterviewSearchRequest extends FormRequest
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

    public function rules(): array
    {
        return [
            'search' => 'sometimes|string|nullable',
            'job_id' => 'sometimes|integer|exists:ci_rec_jobs,job_id|nullable',
            'status' => 'sometimes|integer|nullable',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'paginate' => 'sometimes|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'البحث',
            'job_id' => 'الوظيفة',
            'status' => 'الحالة',
            'per_page' => 'عدد النتائج لكل صفحة',
            'page' => 'رقم الصفحة',
            'paginate' => 'التصفح المرقّم',
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
