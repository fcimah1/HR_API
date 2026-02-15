<?php

namespace App\Http\Requests\Recruitment\Candidate;

use App\Enums\Recruitment\CandidateStatusEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateCandidateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        // Support 'status' as an alias for 'application_status'
        if (!$this->has('application_status') && $this->has('status')) {
            $this->merge(['application_status' => $this->status]);
        }

        if ($this->has('application_status')) {
            $input = trim((string)$this->application_status);

            // Normalize Arabic characters for flexible matching
            $normalize = function ($str) {
                return str_replace(
                    ['ة', 'أ', 'إ', 'آ', 'ى'],
                    ['ه', 'ا', 'ا', 'ا', 'ي'],
                    $str
                );
            };

            $statusMap = [
                'دعوه للمقابله' => CandidateStatusEnum::invited_to_interview()->value,
                'invited_to_interview' => CandidateStatusEnum::invited_to_interview()->value,
                'مرفوض' => CandidateStatusEnum::rejected()->value,
                'rejected' => CandidateStatusEnum::rejected()->value,
            ];

            $normalizedInput = $normalize($input);
            $mappedValue = $statusMap[$input] ?? $statusMap[$normalizedInput] ?? $this->application_status;

            $this->merge([
                'application_status' => $mappedValue,
            ]);
        }
    }

    public function rules(): array
    {
        $permissionService = resolve(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());
        $invitedStatus = CandidateStatusEnum::invited_to_interview()->value;

        return [
            'application_status' => ['required', Rule::in([CandidateStatusEnum::rejected()->value, CandidateStatusEnum::invited_to_interview()->value])],
            'interview_date' => "required_if:application_status,{$invitedStatus}|date-format:Y-m-d|nullable",
            'interview_time' => "required_if:application_status,{$invitedStatus}|string|date_format:H:i|nullable",
            'interview_place' => "required_if:application_status,{$invitedStatus}|string|nullable",
            'interviewer_id' => [
                "required_if:application_status,{$invitedStatus}",
                'integer',
                'nullable',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                }),
            ],
            'description' => "required_if:application_status,{$invitedStatus}|string|nullable",
        ];
    }

    public function messages(): array
    {
        return [
            'application_status.required' => 'الحالة مطلوبة',
            'application_status.enum' => 'الحالة غير صالحة',
            'interview_date.required' => 'تاريخ المقابلة مطلوب',
            'interview_date.date_format' => 'تاريخ المقابلة غير صالح',
            'interview_time.required' => 'وقت المقابلة مطلوب',
            'interview_time.date_format' => 'وقت المقابلة غير صالح',
            'interview_place.required' => 'مكان المقابلة مطلوب',
            'interviewer_id.required' => 'المحاور مطلوب',
            'interviewer_id.integer' => 'المحاور غير صالح',
            'interviewer_id.exists' => 'المحاور غير موجود',
            'description.required' => 'الوصف مطلوب',
            'description.string' => 'الوصف غير صالح',
        ];
    }

    public function attributes(): array
    {
        return [
            'application_status' => 'حالة الطلب',
            'interview_date' => 'تاريخ المقابلة',
            'interview_time' => 'وقت المقابلة',
            'interview_place' => 'مكان المقابلة',
            'interviewer_id' => 'المحاور',
            'description' => 'الوصف',
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
