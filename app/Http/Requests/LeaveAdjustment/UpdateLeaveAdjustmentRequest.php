<?php

namespace App\Http\Requests\LeaveAdjustment;

use App\Models\ErpConstant;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     schema="UpdateLeaveAdjustmentRequest",
 *     type="object",
 *     title="Update Leave Adjustment Request",
 *     required={"leave_type_id", "adjust_hours", "reason_adjustment", "adjustment_date", "duty_employee_id"},
 *     @OA\Property(property="leave_type_id", type="integer", description="Leave type ID"),
 *     @OA\Property(property="adjust_hours", type="number", format="float", description="Adjustment hours"),
 *     @OA\Property(property="reason_adjustment", type="string", description="Reason for adjustment"),
 *     @OA\Property(property="adjustment_date", type="string", format="date", description="Adjustment date"),
 *     @OA\Property(property="duty_employee_id", type="integer", description="Duty employee ID")
 * )
 */

class UpdateLeaveAdjustmentRequest extends FormRequest
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
        $user = $this->user();
        return [
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
            'adjust_hours' => [
                'required',
                'numeric',
                'min:0.5', // على الأقل نصف ساعة
            ],
            'reason_adjustment' => 'sometimes|string|max:1000',
            'adjustment_date' => 'sometimes|date',
            'operator' => 'nullable|string|in:add,sub',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'leave_type_id.exists' => 'نوع الإجازة المحدد غير صحيح',
            'adjust_hours.required' => 'ساعات التسوية مطلوبة',
            'adjust_hours.numeric' => 'ساعات التسوية يجب أن تكون رقم',
            'adjust_hours.min' => 'ساعات التسوية لا يجب أن تقل عن 0.5 ساعة',
            'reason_adjustment.max' => 'سبب التسوية لا يجب أن يتجاوز 1000 حرف',
            'adjustment_date.date' => 'تاريخ التسوية يجب أن يكون تاريخاً صحيحاً',

        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if leave type belongs to user's company using effective company id
            if ($this->filled('leave_type_id')) {
                $user = $this->user();
                $permissionService = app(\App\Services\SimplePermissionService::class);
                $companyId = $permissionService->getEffectiveCompanyId($user);

                $leaveModel = new LeaveApplication();
                $validTypes = $leaveModel->allLeaveTypeNameByCompanyId($companyId);
                $availableIds = array_keys($validTypes);

                if (!in_array($this->leave_type_id, $availableIds)) {
                    $validator->errors()->add('leave_type_id', 'نوع الإجازة غير متاح لشركتك');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        Log::warning('فشل تحديث تسوية إجازة', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تحديث تسوية إجازة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
