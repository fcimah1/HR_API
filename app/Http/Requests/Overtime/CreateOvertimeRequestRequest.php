<?php

declare(strict_types=1);

namespace App\Http\Requests\Overtime;

use App\Enums\OvertimeReasonEnum;
use App\Enums\CompensationTypeEnum;
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
 *     @OA\Property(property="overtime_reason", type="string", example="STANDBY_PAY", enum={"STANDBY_PAY", "WORK_THROUGH_LUNCH", "OUT_OF_TOWN", "SALARIED_EMPLOYEE", "ADDITIONAL_WORK_HOURS"}, description="سبب العمل الإضافي"),
 *     @OA\Property(property="additional_work_hours", type="integer", example=0, description="نوع ساعات العمل (0-3)"),
 *     @OA\Property(property="compensation_type", type="string", example="BANKED", enum={"BANKED", "PAYOUT"}, description="نوع التعويض"),
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
            'request_date' => 'required|date|date_format:Y-m-d|after_or_equal:today',
            'clock_in' => ['required', 'date_format:g:i A'],
            'clock_out' => ['required', 'date_format:g:i A', 'after:clock_in'],
            'overtime_reason' => ['required', 'string', function ($attribute, $value, $fail) {
                $validNames = array_column(OvertimeReasonEnum::cases(), 'name');
                if (!in_array($value, $validNames, true)) {
                    $fail('سبب العمل الإضافي المحدد غير صالح. القيم المسموحة هي: ' . implode(', ', $validNames));
                }
            }],
            'additional_work_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'compensation_type' => ['required', 'string', function ($attribute, $value, $fail) {
                $validNames = array_column(CompensationTypeEnum::cases(), 'name');
                if (!in_array($value, $validNames, true)) {
                    $fail('نوع التعويض المحدد غير صالح. القيم المسموحة هي: ' . implode(', ', $validNames));
                }
            }],
            'request_reason' => ['nullable', 'string', 'max:1000'],
            'employee_id' => [
                'nullable',
                'integer',
                new \App\Rules\CanRequestForEmployee(),
            ],

        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'request_date.required' => 'حقل تاريخ الطلب مطلوب',
            'request_date.date' => 'حقل تاريخ الطلب يجب أن يكون تاريخاً صالحاً',
            'request_date.date_format' => 'حقل تاريخ الطلب يجب أن يكون بصيغة Y-m-d',
            'request_date.after_or_equal' => 'حقل تاريخ الطلب يجب أن يكون تاريخ اليوم أو تاريخ مستقبلي',
            'clock_in.required' => 'حقل وقت البداية مطلوب',
            'clock_in.date_format' => 'حقل وقت البداية يجب أن يكون بصيغة g:i A (مثال: 2:30 PM)',
            'clock_out.required' => 'حقل وقت النهاية مطلوب',
            'clock_out.date_format' => 'حقل وقت النهاية يجب أن يكون بصيغة g:i A (مثال: 7:00 PM)',
            'clock_out.after' => 'حقل وقت النهاية يجب أن يكون بعد وقت البداية',
            'overtime_reason.required' => 'حقل سبب العمل الإضافي مطلوب',
            'overtime_reason.string' => 'حقل سبب العمل الإضافي يجب أن يكون نصاً',
            'additional_work_hours.numeric' => 'حقل ساعات العمل الإضافية يجب أن يكون رقماً',
            'additional_work_hours.min' => 'حقل ساعات العمل الإضافية يجب أن يكون أكبر من أو يساوي 0',
            'additional_work_hours.max' => 'حقل ساعات العمل الإضافية يجب أن يكون أقل من أو يساوي 24',
            'compensation_type.required' => 'حقل نوع التعويض مطلوب',
            'compensation_type.string' => 'حقل نوع التعويض يجب أن يكون نصاً',
            'request_reason.string' => 'حقل سبب الطلب يجب أن يكون نصاً',
            'request_reason.max' => 'حقل سبب الطلب يجب ألا يزيد عن 1000 حرف',
            'employee_id.integer' => 'حقل معرف الموظف يجب أن يكون رقماً',
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
     * Get validated overtime reason as enum instance
     */
    public function getOvertimeReasonEnum(): OvertimeReasonEnum
    {
        return OvertimeReasonEnum::from($this->validated('overtime_reason'));
    }

    /**
     * Get validated compensation type as enum instance
     */
    public function getCompensationTypeEnum(): CompensationTypeEnum
    {
        return CompensationTypeEnum::from($this->validated('compensation_type'));
    }

    /**
     * Handle a passed validation attempt - validate ADDITIONAL_WORK_HOURS requirement
     */
    protected function passedValidation(): void
    {
        $validated = $this->validator->validated();
        
        // Additional validation: ADDITIONAL_WORK_HOURS requires additional_work_hours field
        if (isset($validated['overtime_reason']) && $validated['overtime_reason'] === 'ADDITIONAL_WORK_HOURS' && $this->additional_work_hours === null) {
            $this->validator->errors()->add(
                'additional_work_hours',
                'يجب تحديد نوع ساعات العمل الإضافية عند اختيار "عمل إضافي"'
            );
            
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

