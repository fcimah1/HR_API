<?php

namespace App\Http\Requests\Leave;

use App\Models\ErpConstant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class CreateHourlyLeaveRequest extends FormRequest
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
        $user = Auth::user();
        
        return [
            'leave_type_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($user) {
                    $permissionService = app(\App\Services\SimplePermissionService::class);
                    $companyId = $permissionService->getEffectiveCompanyId($user);
                    
                    $leaveType = ErpConstant::where('constants_id', $value)
                        ->where('type', ErpConstant::TYPE_LEAVE_TYPE)
                        ->where(function($query) use ($companyId) {
                            $query->where('company_id', $companyId)
                                  ->orWhere('company_id', 0);
                        })
                        ->where('field_three', '1')
                        ->exists();
                    
                    if (!$leaveType) {
                        $fail('نوع الإجازة غير متاح لشركتك');
                    }
                }
            ],
            'duty_employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'date' => 'required|date|after_or_equal:today',
            'clock_in_m' => 'required|date_format:h:i A',
            'clock_out_m' => 'required|date_format:h:i A|after:clock_in_m',
            'reason' => 'required|string|min:10|max:1000',
            'remarks' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'نوع الإجازة مطلوب',
            'duty_employee_id.exists' => 'الموظف البديل يجب أن يكون من نفس الشركة ونشط',
            'date.required' => 'تاريخ الإستئذان مطلوب',
            'date.date' => 'تاريخ غير صالح',
            'date.after_or_equal' => 'يجب أن يكون تاريخ الإستئذان بعد أو يساوي تاريخ اليوم',
            'clock_in_m.required' => 'وقت بداية الإستئذان مطلوب',
            'clock_in_m.date_format' => 'تنسيق وقت غير صحيح. استخدم التنسيق: 01:00 PM',
            'clock_out_m.required' => 'وقت نهاية الإستئذان مطلوب',
            'clock_out_m.date_format' => 'تنسيق وقت غير صحيح. استخدم التنسيق: 02:00 PM',
            'clock_out_m.after' => 'وقت النهاية يجب أن يكون بعد وقت البداية',
            'reason.required' => 'سبب الإستئذان مطلوب',
            'reason.min' => 'يجب أن يكون سبب الإستئذان 10 أحرف على الأقل',
            'reason.max' => 'لا يمكن أن يتجاوز سبب الإستئذان 1000 حرف',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'فشل التحقق من صحة البيانات',
            'errors' => $validator->errors()
        ], 422);

        throw new HttpResponseException($response);
    }
}
