<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class NotificationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module_option' => 'nullable|string',
            'is_read' => 'nullable|in:0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'is_read.in' => 'حالة القراءة غير صحيحة',
            'per_page.min' => 'الحد الأدنى للعناصر هو 1',
            'per_page.max' => 'الحد الأقصى للعناصر هو 100',
        ];
    }
}
