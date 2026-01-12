<?php

declare(strict_types=1);

namespace App\Http\Requests\TrainingSkill;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="UpdateTrainingSkillRequest",
 *     type="object",
 *     title="Update Training Skill Request",
 *     description="طلب تحديث نوع/مهارة تدريب",
 *     required={"name"},
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         example="تطوير البرمجيات المتقدم",
 *         description="اسم نوع/مهارة التدريب"
 *     )
 * )
 */
class UpdateTrainingSkillRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم نوع التدريب مطلوب',
            'name.string' => 'اسم نوع التدريب يجب أن يكون نصاً',
            'name.max' => 'اسم نوع التدريب يجب ألا يزيد عن 200 حرف',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'اسم نوع التدريب',
        ];
    }
}
