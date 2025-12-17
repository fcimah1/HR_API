<?php

namespace App\Http\Requests\HourlyLeave;

use App\Enums\DeductedStatus;
use App\Enums\LeavePlaceEnum;
use App\Models\ErpConstant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
            'employee_id' => [
                'nullable',
                'integer',
                new \App\Rules\CanRequestForEmployee(),
            ],
            'leave_type_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($user) {
                    $permissionService = app(\App\Services\SimplePermissionService::class);
                    $companyId = $permissionService->getEffectiveCompanyId($user);

                    $leaveType = ErpConstant::where('constants_id', $value)
                        ->where('type', ErpConstant::TYPE_LEAVE_TYPE)
                        ->where(function ($query) use ($companyId) {
                            $query->where('company_id', $companyId)
                                ->orWhere('company_id', 0);
                        })
                        ->first();

                    if (!$leaveType) {
                        $fail('نوع الإجازة غير متاح لشركتك');
                    }
                }
            ],
            'duty_employee_id' => [
                'nullable',
                'integer',
                new \App\Rules\ValidDutyEmployee($this->employee_id ?? $user->user_id),
            ],
            'date' => [
                'required',
                'date',
                'after_or_equal:today',
                function ($attribute, $value, $fail) use ($user) {
                    $permissionService = app(\App\Services\SimplePermissionService::class);
                    $companyId = $permissionService->getEffectiveCompanyId($user);

                    // التحقق من عدم وجود استئذان آخر في نفس التاريخ
                    $hourlyLeaveRepository = app(\App\Repository\Interface\HourlyLeaveRepositoryInterface::class);

                    if ($hourlyLeaveRepository->hasHourlyLeaveOnDate($user->user_id, $value, $companyId)) {
                        $fail('يوجد لديك استئذان مسجل بالفعل في هذا التاريخ. لا يمكن تسجيل طلب آخر في نفس اليوم.');
                    }
                }
            ],
            'clock_in_m' => 'required|date_format:h:i A',
            'clock_out_m' => 'required|date_format:h:i A|after:clock_in_m',
            'reason' => 'required|string',
            'remarks' => 'nullable|string|max:1000',
            'is_half_day' => 'nullable|boolean',
            'is_deducted' => ['nullable', 'boolean', Rule::in(DeductedStatus::cases())],
            'place' => ['nullable', 'boolean', Rule::in(LeavePlaceEnum::cases())],
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
            'reason.max' => 'لا يمكن أن يتجاوز سبب الإستئذان 1000 حرف',
            'remarks.max' => 'لا يمكن أن يتجاوز الملاحظات 1000 حرف',
            'is_half_day.boolean' => 'يجب أن يكون إجابة Half Day نعم أو لا',
            'is_deducted.boolean' => 'يجب أن يكون إجابة Deducted نعم أو لا',
            'place.boolean' => 'يجب أن يكون إجابة مكان الإستئذان نعم أو لا',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422);

        throw new HttpResponseException($response);
    }
}
