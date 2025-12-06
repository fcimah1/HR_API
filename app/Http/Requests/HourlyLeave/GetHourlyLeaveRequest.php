<?php

namespace App\Http\Requests\HourlyLeave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class GetHourlyLeaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'status' => 'nullable|string|in:pending,approved,rejected',
            'leave_type_id' => 'nullable|integer',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|string|in:created_at,from_date,status',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'employee_id.exists' => 'الموظف المحدد غير موجود',
            'status.in' => 'الحالة يجب أن تكون: pending, approved, أو rejected',
            'from_date.date' => 'تاريخ البداية غير صحيح',
            'to_date.date' => 'تاريخ النهاية غير صحيح',
            'to_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
            'per_page.max' => 'عدد العناصر في الصفحة يجب ألا يتجاوز 100',
        ];
    }
}

