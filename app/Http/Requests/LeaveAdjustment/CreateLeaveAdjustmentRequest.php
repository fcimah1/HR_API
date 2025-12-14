<?php

namespace App\Http\Requests\LeaveAdjustment;

use App\Models\ErpConstant;
use App\Models\LeaveApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// No logging on validation failures for adjustments to avoid noisy logs


/**
 * @OA\Schema(
 *     schema="CreateLeaveAdjustmentRequest",
 *     type="object",
 *     title="Create Leave Adjustment Request",
 *     required={"leave_type_id", "adjustment_date", "duty_employee_id", "adjust_hours", "reason_adjustment"},
 *     @OA\Property(property="leave_type_id", type="integer", description="Leave type ID"),
 *     @OA\Property(property="adjustment_date", type="string", format="date", description="Adjustment date"),
 *     @OA\Property(property="duty_employee_id", type="integer", description="Duty employee ID"),
 *     @OA\Property(property="adjust_hours", type="number", format="float", description="Adjustment hours"),
 *     @OA\Property(property="reason_adjustment", type="string", description="Reason for adjustment")
 * )
 */

class CreateLeaveAdjustmentRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = Auth::user();
        return [
            'employee_id' => [
                'nullable',
                'integer',
                new \App\Rules\CanRequestForEmployee(),
            ],
            'leave_type_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($user) {
                    // استخدام SimplePermissionService للحصول على معرف الشركة الفعلي
                    $permissionService = app(\App\Services\SimplePermissionService::class);
                    $companyId = $permissionService->getEffectiveCompanyId($user);

                    // التحقق من وجود نوع الإجازة
                    $leaveType =  ErpConstant::where('constants_id', $value)
                        ->where('type',  ErpConstant::TYPE_LEAVE_TYPE)
                        ->where(function ($query) use ($companyId) {
                            $query->where('company_id', $companyId)
                                ->orWhere('company_id', 0); // الأنواع العامة
                        })
                        ->first();
                    // Intentionally not logging here to avoid noise in logs
                    $leaveModel = new LeaveApplication();
                    $validTypes = $leaveModel->allLeaveTypeNameByCompanyId($companyId);
                    Log::info('Valid types: ' . json_encode($validTypes));

                    // Check if the leave type ID exists in the valid types
                    if (!array_key_exists($value, $validTypes)) {
                        $validList = [];
                        foreach ($validTypes as $id => $name) {
                            $validList[] = "[{$id} : ({$name})]";
                        }
                        $fail('نوع الإجازة المحدد غير صالح. القيم المسموحة هي: ' . implode(', ', $validList));
                    }
                }
            ],
            'adjustment_date' => [
                'required',
                'date',
                'after_or_equal:today', // يمنع التواريخ الماضية
                function ($attribute, $value, $fail) use ($user) {
                    $employeeId = $this->employee_id ?? $user->user_id;
                    $exists = \App\Models\LeaveAdjustment::where('employee_id', $employeeId)
                        ->where('adjustment_date', $value)
                        ->where('status', '!=', \App\Models\LeaveAdjustment::STATUS_REJECTED)
                        ->exists();

                    if ($exists) {
                        $fail('يوجد طلب تسوية مسبق لنفس هذا التاريخ.');
                    }
                }
            ],
            'duty_employee_id' => [
                'nullable',
                'integer',
                new \App\Rules\ValidDutyEmployee($this->employee_id ?? $user->user_id),
            ],
            'adjust_hours' => [
                'required',
                'numeric',
                'min:0.5', // على الأقل نصف ساعة
            ],
            'reason_adjustment' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }


    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من صحة طلب إنشاء تسوية إجازة',
            'errors' => $validator->errors(),
        ], 422));
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'يجب تحديد نوع الإجازة',
            'leave_type_id.exists' => 'نوع الإجازة غير صالح',
            'adjustment_date.required' => 'يجب تحديد تاريخ التعديل',
            'adjustment_date.date' => 'يجب إدخال تاريخ صحيح',
            'adjustment_date.after_or_equal' => 'لا يمكن تحديد تاريخ ماضي',
            'duty_employee_id.exists' => 'الموظف البديل غير موجود',
            'duty_employee_id.different' => 'لا يمكن اختيار الموظف نفسه كبديل',
            'adjust_hours.required' => 'يجب تحديد عدد ساعات التعديل',
            'adjust_hours.numeric' => 'يجب إدخال رقم صحيح أو عشري',
            'adjust_hours.min' => 'يجب أن لا يقل عدد الساعات عن 0.5',
            'adjust_hours.max' => 'يجب أن لا يزيد عدد الساعات عن 24 ساعة',
            'reason_adjustment.required' => 'يجب إدخال سبب التعديل',
            'reason_adjustment.string' => 'يجب إدخال نص صالح',
            'reason_adjustment.max' => 'يجب أن لا يتجاوز سبب التعديل 500 حرف'
        ];
    }
}
