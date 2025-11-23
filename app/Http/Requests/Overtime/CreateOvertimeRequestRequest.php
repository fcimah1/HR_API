<?php

namespace App\Http\Requests\Overtime;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="CreateOvertimeRequestRequest",
 *     type="object",
 *     title="Create Overtime Request",
 *     required={"request_date", "clock_in", "clock_out", "overtime_reason", "compensation_type"},
 *     @OA\Property(property="request_date", type="string", format="date", example="2025-11-25", description="تاريخ الطلب"),
 *     @OA\Property(property="clock_in", type="string", example="2:30 PM", description="وقت البداية (صيغة 12 ساعة)"),
 *     @OA\Property(property="clock_out", type="string", example="7:00 PM", description="وقت النهاية (صيغة 12 ساعة)"),
 *     @OA\Property(property="overtime_reason", type="integer", example=1, description="سبب العمل الإضافي (1-5)"),
 *     @OA\Property(property="additional_work_hours", type="integer", example=0, description="نوع ساعات العمل (0-3)"),
 *     @OA\Property(property="compensation_type", type="integer", example=1, description="نوع التعويض (1=مالي, 2=إجازة)"),
 *     @OA\Property(property="request_reason", type="string", example="عمل إضافي", description="سبب الطلب"),
 *     @OA\Property(property="employee_id", type="integer", example=37, description="معرف الموظف (للشركة/HR فقط)")
 * )
 */
class CreateOvertimeRequestRequest extends FormRequest
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
            'request_date' => ['required', 'date', 'date_format:Y-m-d'],
            'clock_in' => ['required', 'date_format:g:i A'],
            'clock_out' => ['required', 'date_format:g:i A', 'after:clock_in'],
            'overtime_reason' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'additional_work_hours' => ['nullable', 'integer', Rule::in([0, 1, 2, 3])],
            'compensation_type' => ['required', 'integer', Rule::in([1, 2])],
            'request_reason' => ['nullable', 'string', 'max:1000'],
            'employee_id' => ['nullable', 'integer', 'exists:ci_erp_users,user_id'], // For company/HR creating on behalf
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'request_date.required' => 'تاريخ الطلب مطلوب',
            'request_date.date' => 'تاريخ الطلب يجب أن يكون تاريخ صحيح',
            'clock_in.required' => 'وقت البداية مطلوب',
            'clock_in.date_format' => 'وقت البداية يجب أن يكون بصيغة 12 ساعة (مثال: 2:30 PM)',
            'clock_out.required' => 'وقت النهاية مطلوب',
            'clock_out.date_format' => 'وقت النهاية يجب أن يكون بصيغة 12 ساعة (مثال: 5:00 PM)',
            'clock_out.after' => 'وقت النهاية يجب أن يكون بعد وقت البداية',
            'overtime_reason.required' => 'سبب العمل الإضافي مطلوب',
            'overtime_reason.in' => 'سبب العمل الإضافي غير صحيح',
            'additional_work_hours.in' => 'نوع ساعات العمل الإضافية غير صحيح',
            'compensation_type.required' => 'نوع التعويض مطلوب',
            'compensation_type.in' => 'نوع التعويض غير صحيح',
            'request_reason.max' => 'السبب يجب ألا يتجاوز 1000 حرف',
            'employee_id.exists' => 'الموظف المحدد غير موجود',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'request_date' => 'تاريخ الطلب',
            'clock_in' => 'وقت البداية',
            'clock_out' => 'وقت النهاية',
            'overtime_reason' => 'سبب العمل الإضافي',
            'additional_work_hours' => 'نوع ساعات العمل',
            'compensation_type' => 'نوع التعويض',
            'request_reason' => 'السبب',
            'employee_id' => 'الموظف',
        ];
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Additional validation: overtime_reason = 5 requires additional_work_hours
        // Note: Use === null instead of empty() because 0 is a valid value
        if ($this->overtime_reason == 5 && $this->additional_work_hours === null) {
            $this->validator->errors()->add(
                'additional_work_hours',
                'يجب تحديد نوع ساعات العمل الإضافية عند اختيار "عمل إضافي"'
            );
            
            // Throw validation exception
            throw new \Illuminate\Validation\ValidationException(
                $this->validator,
                response()->json([
                    'success' => false,
                    'message' => 'يجب تحديد نوع ساعات العمل الإضافية عند اختيار "عمل إضافي"',
                    'errors' => $this->validator->errors()
                ], 422)
            );
        }
    }
}

