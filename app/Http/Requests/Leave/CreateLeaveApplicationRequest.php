<?php

namespace App\Http\Requests\Leave;

use App\Enums\DeductedStatus;
use App\Enums\LeavePlaceEnum;
use App\Models\ErpConstant;
use App\Models\LeaveApplication;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// No logging on validation failures to avoid noisy log output

/**
 * @OA\Schema(
 *     schema="CreateLeaveApplicationRequest",
 *     type="object",
 *     title="Create Leave Application Request",
 *     required={"leave_type_id", "from_date", "to_date", "reason", "duty_employee_id", "is_half_day", "leave_hours", "remarks"},
 *     @OA\Property(property="leave_type_id", type="integer", description="Leave type ID"),
 *     @OA\Property(property="from_date", type="string", format="date", description="From date"),
 *     @OA\Property(property="to_date", type="string", format="date", description="To date"),
 *     @OA\Property(property="reason", type="string", description="Reason for leave"),
 *     @OA\Property(property="duty_employee_id", type="integer", description="Duty employee ID"),
 *     @OA\Property(property="is_half_day", type="boolean", description="Is half day"),
 *     @OA\Property(property="remarks", type="string", description="Remarks")
 * )
 */

class CreateLeaveApplicationRequest extends FormRequest
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
            'from_date' => 'required|date|after_or_equal:today',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'required|string|max:1000',
            'duty_employee_id' => [
                'nullable',
                'integer',
                new \App\Rules\ValidDutyEmployee($this->employee_id ?? $user->user_id),
            ],
            'is_half_day' => 'nullable|boolean',
            'remarks' => 'nullable|string|max:1000',
            'is_deducted' => 'nullable|boolean|in:' . implode(',', array_map(fn($c) => $c->value, DeductedStatus::cases())),
            'place' => 'nullable|boolean|in:' . implode(',', array_map(fn($c) => $c->value, LeavePlaceEnum::cases())),
        ];
    }
    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'نوع الإجازة مطلوب',
            'leave_type_id.exists' => 'نوع الإجازة المحدد غير صحيح',
            'from_date.required' => 'تاريخ بداية الإجازة مطلوب',
            'from_date.date' => 'تاريخ بداية الإجازة غير صحيح',
            'from_date.after_or_equal' => 'تاريخ بداية الإجازة يجب أن يكون بعد أو يساوي تاريخ اليوم',
            'to_date.required' => 'تاريخ نهاية الإجازة مطلوب',
            'to_date.after_or_equal' => 'تاريخ نهاية الإجازة يجب أن يكون بعد أو يساوي تاريخ البداية',
            'reason.required' => 'سبب الإجازة مطلوب',
            'reason.max' => 'سبب الإجازة لا يجب أن يتجاوز 1000 حرف',
            'is_deducted.in' => 'حالة الإجازة يجب أن تكون صحيحة',
            'place.in' => 'مكان الإجازة يجب أن يكون صحيح'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'leave_type_id' => 'نوع الإجازة',
            'from_date' => 'تاريخ البداية',
            'to_date' => 'تاريخ النهاية',
            'reason' => 'سبب الإجازة',
            'duty_employee_id' => 'الموظف البديل',
            'is_half_day' => 'نصف يوم',
            'remarks' => 'ملاحظات',
            'is_deducted' => 'حالة الإجازة',
            'place' => 'مكان الإجازة',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for overlapping leave dates
            if ($this->filled(['from_date', 'to_date'])) {
                $fromDate = new \DateTime($this->from_date);
                $toDate = new \DateTime($this->to_date);

                $user = $this->user();
                $permissionService = app(\App\Services\SimplePermissionService::class);
                $effectiveCompanyId = $permissionService->getEffectiveCompanyId($user);

                // Get the target employee ID (from request or current user)
                $targetEmployeeId = $this->employee_id ?? $user->user_id;

                $from = $fromDate->format('Y-m-d');
                $to = $toDate->format('Y-m-d');

                $hasOverlap = LeaveApplication::where('employee_id', $targetEmployeeId)
                    ->where('company_id', $effectiveCompanyId)
                    ->where(function ($query) use ($from, $to) {
                        $query->where(function ($q) use ($from, $to) {
                            $q->where('from_date', '<=', $to)
                                ->where('to_date', '>=', $from);
                        })
                            ->orWhere(function ($q) use ($from, $to) {
                                $q->whereNotNull('particular_date')
                                    ->whereBetween('particular_date', [$from, $to]);
                            });
                    })
                    ->exists();

                if ($hasOverlap) {
                    $validator->errors()->add('from_date', $from . ' لقد قدمت طلب إجازة في هذه الفترة');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل إنشاء طلب إجازة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
