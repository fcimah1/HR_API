<?php

namespace App\Http\Requests\CustodyClearance;

use Illuminate\Foundation\Http\FormRequest;

class GetCustodiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:working,damaged,disposed',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'الموظف المحدد غير موجود',
            'status.in' => 'حالة الأصل غير صالحة',
        ];
    }
}
