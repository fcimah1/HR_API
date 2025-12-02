<?php

namespace App\Http\Requests\LeaveAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class ApproveLeaveAdjustmentRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject',
            'remarks' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'الإجراء مطلوب',
            'action.in' => 'الإجراء يجب أن يكون approve أو reject',
            'remarks.string' => 'الملاحظات يجب أن تكون نصاً',
            'remarks.max' => 'الملاحظات لا يجب أن تتجاوز 500 حرف',
        ];
    }
}
