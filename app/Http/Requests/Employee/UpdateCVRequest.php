<?php

namespace App\Http\Requests\Employee;

use App\Enums\ExperienceLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCVRequest extends FormRequest
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
     */
    public function rules(): array
    {
        // Get all Arabic labels from the ExperienceLevel enum
        $validExperienceValues = [];
        foreach (ExperienceLevel::cases() as $level) {
            $validExperienceValues[] = $level->getArabicLabel();
        }

        return [
            'bio' => 'required|string|max:2000',
            'experience' => ['required', Rule::in($validExperienceValues)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'bio.string' => 'النبذة الشخصية يجب أن تكون نص',
            'bio.required' => 'النبذة الشخصية مطلوبة',
            'bio.max' => 'النبذة الشخصية يجب أن تكون أقل من 2000 حرف',
            'experience.required' => 'مستوى الخبرة مطلوب',
            'experience.in' => 'مستوى الخبرة غير موجود',
        ];
    }
}