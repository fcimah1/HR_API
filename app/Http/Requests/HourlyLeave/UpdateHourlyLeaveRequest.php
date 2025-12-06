<?php

namespace App\Http\Requests\HourlyLeave;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class UpdateHourlyLeaveRequest extends FormRequest
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
            'date' => 'sometimes|date|after_or_equal:today',
            'clock_in_m' => 'sometimes|required_with:clock_out_m|date_format:h:i A',
            'clock_out_m' => 'sometimes|required_with:clock_in_m|date_format:h:i A|after:clock_in_m',
            'reason' => 'sometimes|string|min:10|max:1000',
            'duty_employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'remarks' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'date.required' => 'تاريخ الإستئذان مطلوب',
            'date.date' => 'تاريخ غير صالح',
            'date.after_or_equal' => 'يجب أن يكون تاريخ الإستئذان بعد أو يساوي تاريخ اليوم',
            'clock_in_m.required_with' => 'وقت بداية الإستئذان مطلوب عند تحديد وقت النهاية',
            'clock_in_m.date_format' => 'تنسيق وقت غير صحيح. استخدم التنسيق: 01:00 PM',
            'clock_out_m.required_with' => 'وقت نهاية الإستئذان مطلوب عند تحديد وقت البداية',
            'clock_out_m.date_format' => 'تنسيق وقت غير صحيح. استخدم التنسيق: 02:00 PM',
            'clock_out_m.after' => 'وقت النهاية يجب أن يكون بعد وقت البداية',
            'reason.min' => 'يجب أن يكون سبب الإستئذان 10 أحرف على الأقل',
            'reason.max' => 'لا يمكن أن يتجاوز سبب الإستئذان 1000 حرف',
            'duty_employee_id.exists' => 'الموظف البديل يجب أن يكون من نفس الشركة ونشط',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // التحقق من أن الساعات أقل من 8 إذا تم تحديث الأوقات
            if ($this->filled(['date', 'clock_in_m', 'clock_out_m'])) {
                try {
                    $startTime = \Carbon\Carbon::parse($this->date . ' ' . $this->clock_in_m);
                    $endTime = \Carbon\Carbon::parse($this->date . ' ' . $this->clock_out_m);
                    $leaveHours = $endTime->diffInHours($startTime);

                    if ($leaveHours >= 8) {
                        $validator->errors()->add('clock_out_m', 'لا يمكن تسجيل استئذان لـ ' . $leaveHours . ' ساعة. الاستئذان يجب أن يكون أقل من 8 ساعات.');
                    }
                } catch (\Exception $e) {
                    $validator->errors()->add('clock_out_m', 'خطأ في حساب ساعات الإستئذان');
                }
            }

            // التحقق من عدم وجود استئذان آخر في نفس التاريخ إذا تم تحديث التاريخ
            if ($this->filled('date')) {
                $permissionService = app(\App\Services\SimplePermissionService::class);
                $companyId = $permissionService->getEffectiveCompanyId(Auth::user());
                
                $hourlyLeaveRepository = app(\App\Repository\Interface\HourlyLeaveRepositoryInterface::class);
                
                // الحصول على معرف الطلب الحالي من route
                $currentLeaveId = $this->route('id');
                
                // التحقق من وجود استئذان آخر في نفس التاريخ (باستثناء الطلب الحالي)
                $existingLeave = \App\Models\LeaveApplication::where('company_id', $companyId)
                    ->where('employee_id', Auth::id())
                    ->whereColumn('from_date', 'to_date')
                    ->where('from_date', $this->date)
                    ->where('leave_hours', '>', 0)
                    ->where('leave_hours', '<', 8)
                    ->whereIn('status', [1, 2])
                    ->where('leave_id', '!=', $currentLeaveId)
                    ->exists();
                
                if ($existingLeave) {
                    $validator->errors()->add('date', 'يوجد لديك استئذان مسجل بالفعل في هذا التاريخ. لا يمكن تسجيل طلب آخر في نفس اليوم.');
                }
            }
        });
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

