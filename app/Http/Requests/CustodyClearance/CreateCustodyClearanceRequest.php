<?php

namespace App\Http\Requests\CustodyClearance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateCustodyClearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'clearance_date' => 'required|date|after_or_equal:today',
            'clearance_type' => 'required|string|in:resignation,termination,transfer,other',
            'asset_ids' => 'nullable|array',
            'asset_ids.*' => 'integer|exists:ci_assets,assets_id',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'clearance_date.required' => 'تاريخ الإخلاء مطلوب',
            'clearance_date.after_or_equal' => 'تاريخ الإخلاء يجب أن يكون اليوم أو بعده',
            'clearance_type.required' => 'نوع الإخلاء مطلوب',
            'clearance_type.in' => 'نوع الإخلاء غير صالح',
            'asset_ids.array' => 'قائمة الأصول يجب أن تكون مصفوفة',
            'asset_ids.*.exists' => 'أحد الأصول المحددة غير موجود',
            'employee_id.exists' => 'الموظف المحدد غير موجود',
        ];
    }
}
