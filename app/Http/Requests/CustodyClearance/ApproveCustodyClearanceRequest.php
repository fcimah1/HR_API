<?php

namespace App\Http\Requests\CustodyClearance;

use Illuminate\Foundation\Http\FormRequest;

class ApproveCustodyClearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|string|in:approve,reject',
            'remarks' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'الإجراء مطلوب',
            'action.in' => 'الإجراء يجب أن يكون موافقة أو رفض',
            'remarks.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف',
        ];
    }
}
