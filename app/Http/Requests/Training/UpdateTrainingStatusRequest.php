<?php

declare(strict_types=1);

namespace App\Http\Requests\Training;

use App\Enums\TrainingStatusEnum;
use App\Enums\TrainingPerformanceEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="UpdateTrainingStatusRequest",
 *     type="object",
 *     title="Update Training Status Request",
 *     description="طلب تحديث حالة وأداء التدريب",
 *     required={"status"},
 *     @OA\Property(
 *         property="status",
 *         type="integer",
 *         enum={0, 1, 2, 3},
 *         example=2,
 *         description="حالة التدريب: 0=قيد الانتظار, 1=بدأ, 2=مكتمل, 3=مرفوض"
 *     ),
 *     @OA\Property(
 *         property="performance",
 *         type="integer",
 *         enum={0, 1, 2, 3, 4},
 *         example=4,
 *         description="مستوى الأداء: 0=غير منتهى, 1=مرضٍ, 2=متوسط, 3=ضعيف, 4=ممتاز"
 *     ),
 *     @OA\Property(
 *         property="remarks",
 *         type="string",
 *         example="تم الانتهاء من التدريب بنجاح",
 *         description="ملاحظات"
 *     )
 * )
 */
class UpdateTrainingStatusRequest extends FormRequest
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
            'status' => ['required', 'integer', function ($attribute, $value, $fail) {
                if (TrainingStatusEnum::tryFrom((int) $value) === null) {
                    $validValues = implode(', ', array_map(fn($e) => $e->value . '=' . $e->label(), TrainingStatusEnum::cases()));
                    $fail("حالة التدريب غير صالحة. القيم المسموحة: {$validValues}");
                }
            }],
            'performance' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if ($value !== null && TrainingPerformanceEnum::tryFrom((int) $value) === null) {
                    $validValues = implode(', ', array_map(fn($e) => $e->value . '=' . $e->label(), TrainingPerformanceEnum::cases()));
                    $fail("مستوى الأداء غير صالح. القيم المسموحة: {$validValues}");
                }
            }],
            'remarks' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'حالة التدريب مطلوبة',
            'status.integer' => 'حالة التدريب يجب أن تكون رقماً',
            'performance.integer' => 'مستوى الأداء يجب أن يكون رقماً',
            'remarks.string' => 'الملاحظات يجب أن تكون نصاً',
            'remarks.max' => 'الملاحظات يجب ألا تزيد عن 1000 حرف',
            'remarks.required' => 'الملاحظات مطلوبة',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'status' => 'حالة التدريب',
            'performance' => 'مستوى الأداء',
            'remarks' => 'الملاحظات',
        ];
    }

    /**
     * Get the status enum value
     */
    public function getStatusEnum(): TrainingStatusEnum
    {
        return TrainingStatusEnum::from((int) $this->validated('status'));
    }

    /**
     * Get the performance enum value if provided
     */
    public function getPerformanceEnum(): ?TrainingPerformanceEnum
    {
        $performance = $this->validated('performance');
        return $performance !== null ? TrainingPerformanceEnum::from((int) $performance) : null;
    }
}
