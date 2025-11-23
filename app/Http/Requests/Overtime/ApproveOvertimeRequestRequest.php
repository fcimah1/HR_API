<?php

namespace App\Http\Requests\Overtime;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="ApproveOvertimeRequestRequest",
 *     type="object",
 *     title="Approve Overtime Request",
 *     @OA\Property(property="remarks", type="string", example="موافق عليه", description="ملاحظات الموافقة (اختياري، حد أقصى 500 حرف)")
 * )
 */
class ApproveOvertimeRequestRequest extends FormRequest
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
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'remarks.max' => 'الملاحظات يجب ألا تتجاوز 500 حرف',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'remarks' => 'الملاحظات',
        ];
    }
}

