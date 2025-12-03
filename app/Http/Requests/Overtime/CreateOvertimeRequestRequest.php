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
            'request_date' => ['required', 'date', 'date_format:Y-m-d'],
            'clock_in' => ['required', 'date_format:g:i A'],
            'clock_out' => ['required', 'date_format:g:i A', 'after:clock_in'],
            'overtime_reason' => ['required', 'string', function ($attribute, $value, $fail) {
                $validNames = array_column(OvertimeReasonEnum::cases(), 'name');
                if (!in_array($value, $validNames, true)) {
                    $fail('The selected overtime reason is invalid.');
                }
            }],
            'additional_work_hours' => ['nullable', 'integer', Rule::in([0, 1, 2, 3])],
            'compensation_type' => ['required', 'string', function ($attribute, $value, $fail) {
                $validNames = array_column(CompensationTypeEnum::cases(), 'name');
                if (!in_array($value, $validNames, true)) {
                    $fail('The selected compensation type is invalid.');
                }
            }],
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
     * Handle a passed validation attempt - convert enum names to integer values
     */
    protected function passedValidation(): void
    {
        // Convert enum names to their integer values for backward compatibility with DTOs/Services
        $validated = $this->validator->validated();
        
        // Convert overtime_reason enum name to integer
        if (isset($validated['overtime_reason']) && is_string($validated['overtime_reason'])) {
            // Get enum by name (e.g., "STANDBY_PAY" -> OvertimeReasonEnum::STANDBY_PAY)
            $overtimeEnum = constant(OvertimeReasonEnum::class . '::' . $validated['overtime_reason']);
            $this->merge(['overtime_reason' => $overtimeEnum->value]);
            
            // Additional validation: ADDITIONAL_WORK_HOURS requires additional_work_hours field
            if ($overtimeEnum === OvertimeReasonEnum::ADDITIONAL_WORK_HOURS && $this->additional_work_hours === null) {
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
        
        // Convert compensation_type enum name to integer
        if (isset($validated['compensation_type']) && is_string($validated['compensation_type'])) {
            // Get enum by name (e.g., "BANKED" -> CompensationTypeEnum::BANKED)
            $compensationEnum = constant(CompensationTypeEnum::class . '::' . $validated['compensation_type']);
            $this->merge(['compensation_type' => $compensationEnum->value]);
        }
    }
}

