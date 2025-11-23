<?php

namespace App\Http\Requests\Overtime;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="RejectOvertimeRequestRequest",
 *     type="object",
 *     title="Reject Overtime Request",
 *     required={"reason"},
 *     @OA\Property(property="reason", type="string", example="لا يوجد ضغط عمل كافي", description="سبب الرفض (مطلوب، حد أقصى 500 حرف)")
 * )
 */
class RejectOvertimeRequestRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'سبب الرفض مطلوب',
            'reason.max' => 'سبب الرفض يجب ألا يتجاوز 500 حرف',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'reason' => 'سبب الرفض',
        ];
    }
}

