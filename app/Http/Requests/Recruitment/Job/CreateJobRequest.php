<?php

namespace App\Http\Requests\Recruitment\Job;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Enums\JobTypeEnum;
use App\Enums\ExperienceLevel;
use App\Enums\GenderEnum;
use App\Enums\Recruitment\JobStatusEnum;
use Illuminate\Support\Facades\Auth;

class CreateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $data = [];

        if ($this->has('gender')) {
            $genderMap = [
                'ذكر' => 0,
                'male' => 0,
                'انثى' => 1,
                'أنثى' => 1,
                'female' => 1,
                'غير محدد' => 2,
                'no_preference' => 2,
            ];
            $data['gender'] = $genderMap[trim($this->gender)] ?? $this->gender;
        }

        if ($this->has('job_type')) {
            $jobTypeMap = [
                'دائم' => 1,
                'permanent' => 1,
                'دوام جزئي' => 0,
                'دوام جزئى' => 0,
                'part_time' => 0,
                'عقد' => 2,
                'contract' => 2,
                'تجريبي' => 3,
                'تجريبى' => 3,
                'probation' => 3,
            ];
            $data['job_type'] = $jobTypeMap[trim($this->job_type)] ?? $this->job_type;
        }

        if ($this->has('status')) {
            $statusMap = [
                'تم النشر' => 1,
                'published' => 1,
                'غير منشور' => 0,
                'unpublished' => 0,
            ];
            $data['status'] = $statusMap[trim((string)$this->status)] ?? $this->status;
        }

        if ($this->has('minimum_experience')) {
            $experienceMap = [
                'حديث التخرج' => 0,
                'سنة' => 1,
                'سنه' => 1,
                'سنتان' => 2,
                '3 سنوات' => 3,
                '4 سنوات' => 4,
                '5 سنوات' => 5,
                '6 سنوات' => 6,
                '7 سنوات' => 7,
                '8 سنوات' => 8,
                '9 سنوات' => 9,
                '10 سنوات' => 10,
                'أكثر من 10 سنوات' => 11,
                '10+ سنوات' => 11,
            ];
            $data['minimum_experience'] = $experienceMap[trim($this->minimum_experience)] ?? $this->minimum_experience;
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $permissionService = resolve(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'job_title' => 'required|string|max:255',
            'designation_id' => [
                'required',
                'integer',
                Rule::exists('ci_designations', 'designation_id')
                    ->where('company_id', $effectiveCompanyId)
            ],
            'job_type' => [
                'required',
                Rule::enum(JobTypeEnum::class)
            ],
            'job_vacancy' => 'required|integer|min:1',
            'gender' => [
                'required',
                Rule::enum(GenderEnum::class)
            ],
            'minimum_experience' => [
                'required',
                Rule::enum(ExperienceLevel::class)
            ],
            'date_of_closing' => 'required|date|after_or_equal:today',
            'short_description' => 'string',
            'long_description' => 'string',
            'status' => ['required', Rule::enum(JobStatusEnum::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'job_title.required' => 'العنوان مطلوب',
            'job_title.string' => 'العنوان يجب أن يكون نصاً',
            'job_title.max' => 'العنوان يجب أن يكون 255 حرفاً',
            'designation_id.required' => 'المسمى الوظيفى مطلوب',
            'designation_id.integer' => 'المسمى الوظيفى يجب أن يكون رقماً',
            'designation_id.exists' => 'المسمى الوظيفى غير موجود',
            'job_type.required' => 'النوع مطلوب',
            'job_type.enum' => 'النوع غير صحيح',
            'job_vacancy.required' => 'الفراغ مطلوب',
            'job_vacancy.integer' => 'الفراغ يجب أن يكون رقماً',
            'job_vacancy.min' => 'الفراغ يجب أن يكون أكبر من 0',
            'gender.required' => 'الجنس مطلوب',
            'gender.enum' => 'الجنس غير صحيح',
            'minimum_experience.required' => 'الخبرة المطلوبة مطلوبة',
            'minimum_experience.enum' => 'الخبرة المطلوبة غير صحيح',
            'date_of_closing.required' => 'تاريخ الإغلاق مطلوب',
            'date_of_closing.date' => 'تاريخ الإغلاق يجب أن يكون تاريخ',
            'date_of_closing.after_or_equal' => 'تاريخ الإغلاق يجب أن يكون تاريخاً في المستقبل',
            'short_description.string' => 'الوصف يجب أن يكون نصاً',
            'long_description.string' => 'الوصف المطول يجب أن يكون نصاً',
            'status.required' => 'الحالة مطلوبة',
            'status.enum' => 'الحالة غير صحيح',
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
