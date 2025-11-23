<?php

namespace App\Http\Requests\Leave;

use App\Services\SimplePermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Schema(
 *     schema="UpdateLeaveTypeRequest",
 *     type="object",
 *     title="Update Leave Type Request",
 *     required={"leave_type_name", "requires_approval"},
 *     @OA\Property(property="leave_type_name", type="string", description="Leave type name"),
 *     @OA\Property(property="requires_approval", type="boolean", description="Requires approval")
 * )
 */

class UpdateLeaveTypeRequest extends FormRequest
{
    public $simplePermissionService;
    public function __construct(
        private readonly SimplePermissionService $permissionService
    ) {
        $this->simplePermissionService = $permissionService;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();

        // return $this->simplePermissionService->checkPermission($user, 'leave_type3');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $leaveTypeId = $this->route('id'); // Get leave type ID from route

        return [
            'leave_type_name' => 'required|string|max:255|unique:ci_erp_constants,category_name,' . $leaveTypeId . ',constants_id',
            'requires_approval' => 'nullable|boolean',
            'is_paid_leave' => 'nullable|boolean',
            'enable_leave_accrual' => 'nullable|boolean',
            'is_carry' => 'nullable|boolean',
            'carry_limit' => 'nullable|numeric|min:0',
            'is_negative_quota' => 'nullable|boolean',
            'negative_limit' => 'nullable|numeric|min:0',
            'is_quota' => 'nullable|boolean',
            'quota_assign' => 'nullable|array',
            'quota_assign.*' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'leave_type_name.required' => 'اسم نوع الإجازة مطلوب',
            'leave_type_name.unique' => 'اسم نوع الإجازة موجود بالفعل',
            'leave_type_name.string' => 'اسم نوع الإجازة يجب أن يكون نص',
            'leave_type_name.max' => 'اسم نوع الإجازة لا يجب أن يتجاوز 255 حرف',
            'requires_approval.boolean' => 'حقل يتطلب موافقة يجب أن يكون قيمة منطقية (0 أو 1)',
            'is_paid_leave.boolean' => 'حقل إجازة مدفوعة يجب أن يكون قيمة منطقية (0 أو 1)',
            'enable_leave_accrual.boolean' => 'حقل تمكين استحقاق الإجازة يجب أن يكون قيمة منطقية (0 أو 1)',
            'is_carry.boolean' => 'حقل الترحيل يجب أن يكون قيمة منطقية (0 أو 1)',
            'carry_limit.numeric' => 'الحد المتاح للترحيل يجب أن يكون رقم',
            'carry_limit.min' => 'الحد المتاح للترحيل يجب أن يكون أكبر من أو يساوي 0',
            'is_negative_quota.boolean' => 'حقل رصيد الإدارة يجب أن يكون قيمة منطقية (0 أو 1)',
            'negative_limit.numeric' => 'رصيد الحادثة المستحق يجب أن يكون رقم',
            'negative_limit.min' => 'رصيد الحادثة المستحق يجب أن يكون أكبر من أو يساوي 0',
            'is_quota.boolean' => 'حقل تخصيص النسبة السنوية يجب أن يكون قيمة منطقية (0 أو 1)',
            'quota_assign.array' => 'تخصيص النسبة السنوية يجب أن يكون مصفوفة',
            'quota_assign.*.numeric' => 'قيم تخصيص النسبة السنوية يجب أن تكون أرقام',
            'quota_assign.*.min' => 'قيم تخصيص النسبة السنوية يجب أن تكون أكبر من أو تساوي 0',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'leave_type_name' => 'اسم نوع الإجازة',
            'requires_approval' => 'يتطلب موافقة',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization()
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بتعديل أنواع الإجازات'
            ], 403)
        );
    }

    protected function failedValidation(Validator $validator)
    {
        Log::warning('فشل تعديل نوع إجازة', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تعديل نوع إجازة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
