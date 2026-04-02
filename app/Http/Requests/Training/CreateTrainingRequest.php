<?php

declare(strict_types=1);

namespace App\Http\Requests\Training;

use App\Enums\TrainingStatusEnum;
use App\Enums\TrainingPerformanceEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="CreateTrainingRequest",
 *     type="object",
 *     title="Create Training Request",
 *     description="طلب إنشاء تدريب جديد",
 *     required={"training_type_id", "trainer_id", "employee_ids", "start_date", "finish_date"},
 *     @OA\Property(
 *         property="training_type_id",
 *         type="integer",
 *         example=1,
 *         description="معرف نوع التدريب"
 *     ),
 *     @OA\Property(
 *         property="trainer_id",
 *         type="integer",
 *         example=1,
 *         description="معرف المدرب"
 *     ),
 *     @OA\Property(
 *         property="employee_ids",
 *         type="array",
 *         @OA\Items(type="integer"),
 *         example={1, 2, 3},
 *         description="معرفات الموظفين المشاركين"
 *     ),
 *     @OA\Property(
 *         property="department_id",
 *         type="integer",
 *         example=1,
 *         description="معرف القسم (اختياري)"
 *     ),
 *     @OA\Property(
 *         property="start_date",
 *         type="string",
 *         format="date",
 *         example="2026-01-15",
 *         description="تاريخ بداية التدريب"
 *     ),
 *     @OA\Property(
 *         property="finish_date",
 *         type="string",
 *         format="date",
 *         example="2026-01-20",
 *         description="تاريخ نهاية التدريب"
 *     ),
 *     @OA\Property(
 *         property="training_cost",
 *         type="number",
 *         format="float",
 *         example=1500.00,
 *         description="تكلفة التدريب"
 *     ),
 *     @OA\Property(
 *         property="training_status",
 *         type="integer",
 *         enum={0, 1, 2, 3},
 *         example=0,
 *         description="حالة التدريب: 0=قيد الانتظار, 1=بدأ, 2=مكتمل, 3=مرفوض"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         example="دورة تدريبية في تطوير PHP",
 *         description="وصف التدريب"
 *     ),
 *     @OA\Property(
 *         property="associated_goals",
 *         type="string",
 *         example="تطوير مهارات البرمجة",
 *         description="الأهداف المرتبطة"
 *     ),
 *     @OA\Property(
 *         property="performance",
 *         type="integer",
 *         enum={0, 1, 2, 3, 4},
 *         example=0,
 *         description="مستوى الأداء: 0=غير منتهى, 1=مرضٍ, 2=متوسط, 3=ضعيف, 4=ممتاز"
 *     ),
 *     @OA\Property(
 *         property="remarks",
 *         type="string",
 *         example="ملاحظات إضافية",
 *         description="ملاحظات"
 *     )
 * )
 */
class CreateTrainingRequest extends FormRequest
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
            'training_type_id' => ['required', 'integer', 'exists:ci_erp_constants,constants_id'],
            'trainer_id' => ['required', 'integer', 'exists:ci_trainers,trainer_id'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:ci_erp_users,user_id'],
            'department_id' => ['nullable', 'integer', 'exists:ci_departments,department_id'],
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'finish_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'training_cost' => ['nullable', 'numeric', 'min:0'],
            'training_status' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if (TrainingStatusEnum::tryFrom((int) $value) === null) {
                    $fail('حالة التدريب غير صالحة. القيم المسموحة: 0-3');
                }
            }],
            'description' => ['nullable', 'string', 'max:2000'],
            'associated_goals' => ['nullable', 'string', 'max:1000'],
            'performance' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if (TrainingPerformanceEnum::tryFrom((int) $value) === null) {
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
            'training_type_id.required' => 'نوع التدريب مطلوب',
            'training_type_id.integer' => 'نوع التدريب يجب أن يكون رقماً',
            'training_type_id.exists' => 'نوع التدريب غير موجود',
            'trainer_id.required' => 'المدرب مطلوب',
            'trainer_id.integer' => 'المدرب يجب أن يكون رقماً',
            'trainer_id.exists' => 'المدرب غير موجود',
            'employee_ids.required' => 'الموظفين المشاركين مطلوبين',
            'employee_ids.array' => 'الموظفين يجب أن يكونوا مصفوفة',
            'employee_ids.min' => 'يجب اختيار موظف واحد على الأقل',
            'employee_ids.*.integer' => 'معرف الموظف يجب أن يكون رقماً',
            'employee_ids.*.exists' => 'أحد الموظفين غير موجود',
            'department_id.integer' => 'القسم يجب أن يكون رقماً',
            'department_id.exists' => 'القسم غير موجود',
            'start_date.required' => 'تاريخ البداية مطلوب',
            'start_date.date' => 'تاريخ البداية يجب أن يكون تاريخاً صالحاً',
            'start_date.date_format' => 'تاريخ البداية يجب أن يكون بصيغة Y-m-d',
            'finish_date.required' => 'تاريخ النهاية مطلوب',
            'finish_date.date' => 'تاريخ النهاية يجب أن يكون تاريخاً صالحاً',
            'finish_date.date_format' => 'تاريخ النهاية يجب أن يكون بصيغة Y-m-d',
            'finish_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
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
