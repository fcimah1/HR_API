<?php

namespace App\Http\Requests\Recruitment\Candidate;

use App\Enums\Recruitment\CandidateStatusEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CandidateSearchRequest extends FormRequest
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
                'قيد الانتظار' => CandidateStatusEnum::pending()->value,
                'pending' => CandidateStatusEnum::pending()->value,
                'دعوه للمقابله' => CandidateStatusEnum::invited_to_interview()->value,
                'invited_to_interview' => CandidateStatusEnum::invited_to_interview()->value,
                'مرفوض' => CandidateStatusEnum::rejected()->value,
                'rejected' => CandidateStatusEnum::rejected()->value,
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

    public function messages(): array
    {
        return [
            'search.string' => 'حقل البحث يجب أن يكون نصًا',
            'job_id.integer' => 'حقل الوظيفة يجب أن يكون رقمًا',
            'job_id.exists' => 'الوظيفة المحددة غير موجودة',
            'status.integer' => 'حقل الحالة يجب أن يكون رقمًا',
            'per_page.integer' => 'حقل عدد النتائج لكل صفحة يجب أن يكون رقمًا',
            'per_page.min' => 'يجب أن يكون عدد النتائج لكل صفحة على الأقل 1',
            'per_page.max' => 'يجب أن يكون عدد النتائج لكل صفحة على الأكثر 100',
            'page.integer' => 'حقل رقم الصفحة يجب أن يكون رقمًا',
            'page.min' => 'يجب أن يكون رقم الصفحة على الأقل 1',
            'paginate.boolean' => 'حقل الترقيم يجب أن يكون قيمة منطقية',
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
            'status' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
