<?php

declare(strict_types=1);

namespace App\Http\Requests\Training;

use App\Enums\TrainingStatusEnum;
use App\Enums\TrainingPerformanceEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="UpdateTrainingRequest",
 *     type="object",
 *     title="Update Training Request",
 *     description="طلب تحديث تدريب",
 *     @OA\Property(property="training_type_id", type="integer", example=1, description="معرف نوع التدريب"),
 *     @OA\Property(property="trainer_id", type="integer", example=1, description="معرف المدرب"),
 *     @OA\Property(property="employee_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3}, description="معرفات الموظفين"),
 *     @OA\Property(property="department_id", type="integer", example=1, description="معرف القسم"),
 *     @OA\Property(property="start_date", type="string", format="date", example="2026-01-15", description="تاريخ البداية"),
 *     @OA\Property(property="finish_date", type="string", format="date", example="2026-01-20", description="تاريخ النهاية"),
 *     @OA\Property(property="training_cost", type="number", format="float", example=1500.00, description="تكلفة التدريب"),
 *     @OA\Property(property="training_status", type="integer", enum={0, 1, 2, 3}, example=1, description="حالة التدريب"),
 *     @OA\Property(property="description", type="string", example="وصف محدث", description="الوصف"),
 *     @OA\Property(property="associated_goals", type="string", example="أهداف محدثة", description="الأهداف"),
 *     @OA\Property(property="performance", type="integer", enum={0, 1, 2, 3, 4}, example=4, description="مستوى الأداء"),
 *     @OA\Property(property="remarks", type="string", example="ملاحظات محدثة", description="الملاحظات")
 * )
 */
class UpdateTrainingRequest extends FormRequest
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
            'training_type_id' => ['nullable', 'integer', 'exists:ci_erp_constants,constants_id'],
            'trainer_id' => ['nullable', 'integer', 'exists:ci_trainers,trainer_id'],
            'employee_ids' => ['nullable', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:ci_erp_users,user_id'],
            'department_id' => ['nullable', 'integer', 'exists:ci_departments,department_id'],
            'start_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'finish_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'training_cost' => ['nullable', 'numeric', 'min:0'],
            'training_status' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if ($value !== null && TrainingStatusEnum::tryFrom((int) $value) === null) {
                    $fail('حالة التدريب غير صالحة. القيم المسموحة: 0-3');
                }
            }],
            'description' => ['nullable', 'string', 'max:2000'],
            'associated_goals' => ['nullable', 'string', 'max:1000'],
            'performance' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if ($value !== null && TrainingPerformanceEnum::tryFrom((int) $value) === null) {
                    $fail('مستوى الأداء غير صالح. القيم المسموحة: 0-4');
                }
            }],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'training_type_id.integer' => 'نوع التدريب يجب أن يكون رقماً',
            'training_type_id.exists' => 'نوع التدريب غير موجود',
            'trainer_id.integer' => 'المدرب يجب أن يكون رقماً',
            'trainer_id.exists' => 'المدرب غير موجود',
            'employee_ids.array' => 'الموظفين يجب أن يكونوا مصفوفة',
            'employee_ids.min' => 'يجب اختيار موظف واحد على الأقل',
            'employee_ids.*.integer' => 'معرف الموظف يجب أن يكون رقماً',
            'employee_ids.*.exists' => 'أحد الموظفين غير موجود',
            'department_id.integer' => 'القسم يجب أن يكون رقماً',
            'department_id.exists' => 'القسم غير موجود',
            'start_date.date' => 'تاريخ البداية يجب أن يكون تاريخاً صالحاً',
            'start_date.date_format' => 'تاريخ البداية يجب أن يكون بصيغة Y-m-d',
            'finish_date.date' => 'تاريخ النهاية يجب أن يكون تاريخاً صالحاً',
            'finish_date.date_format' => 'تاريخ النهاية يجب أن يكون بصيغة Y-m-d',
            'training_cost.numeric' => 'تكلفة التدريب يجب أن تكون رقماً',
            'training_cost.min' => 'تكلفة التدريب يجب أن تكون صفر أو أكثر',
            'description.string' => 'الوصف يجب أن يكون نصاً',
            'description.max' => 'الوصف يجب ألا يزيد عن 2000 حرف',
            'associated_goals.string' => 'الأهداف يجب أن تكون نصاً',
            'associated_goals.max' => 'الأهداف يجب ألا تزيد عن 1000 حرف',
            'remarks.string' => 'الملاحظات يجب أن تكون نصاً',
            'remarks.max' => 'الملاحظات يجب ألا تزيد عن 1000 حرف',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'training_type_id' => 'نوع التدريب',
            'trainer_id' => 'المدرب',
            'employee_ids' => 'الموظفين',
            'department_id' => 'القسم',
            'start_date' => 'تاريخ البداية',
            'finish_date' => 'تاريخ النهاية',
            'training_cost' => 'تكلفة التدريب',
            'training_status' => 'حالة التدريب',
            'description' => 'الوصف',
            'associated_goals' => 'الأهداف',
            'performance' => 'مستوى الأداء',
            'remarks' => 'الملاحظات',
        ];
    }
}
