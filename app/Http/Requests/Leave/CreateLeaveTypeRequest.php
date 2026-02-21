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
 *     schema="CreateLeaveTypeRequest",
 *     type="object",
 *     title="Create Leave Type Request",
 *     required={"leave_type_name"},
 *     @OA\Property(property="leave_type_name",      type="string",  description="اسم نوع الإجازة"),
 *     @OA\Property(property="requires_approval",    type="boolean", description="هل تتطلب موافقة؟"),
 *     @OA\Property(property="is_paid_leave",        type="boolean", description="هل هي إجازة مدفوعة؟"),
 *     @OA\Property(property="enable_leave_accrual", type="boolean", description="تمكين استحقاق الإجازة"),
 *     @OA\Property(property="is_carry",             type="boolean", description="السماح بالترحيل"),
 *     @OA\Property(property="carry_limit",          type="number",  description="الحد الأقصى للترحيل"),
 *     @OA\Property(property="is_negative_quota",    type="boolean", description="السماح برصيد سالب"),
 *     @OA\Property(property="negative_limit",       type="number",  description="الحد الأقصى للرصيد السالب"),
 *     @OA\Property(property="is_quota",             type="boolean", description="تفعيل التخصيص السنوي"),
 *     @OA\Property(property="quota_assign",         type="object",  description="تخصيص الأيام لكل سنة (0-based index، يدعم حتى 50 سنة)", example={"0": 15, "1": 18})
 * )
 */

class CreateLeaveTypeRequest extends FormRequest
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
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'leave_type_name'      => 'required|string|max:255|unique:ci_erp_constants,category_name',
            'requires_approval'    => 'nullable|boolean',
            'is_paid_leave'        => 'nullable|boolean',
            'enable_leave_accrual' => 'nullable|boolean',
            'is_carry'             => 'nullable|boolean',
            'carry_limit'          => 'nullable|numeric|min:0',
            'is_negative_quota'    => 'nullable|boolean',
            'negative_limit'       => 'nullable|numeric|min:0',
            'is_quota'             => 'nullable|boolean',
            'quota_assign'         => 'nullable|array|max:50',
            'quota_assign.*'       => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'leave_type_name.required'      => 'اسم نوع الإجازة مطلوب',
            'leave_type_name.unique'         => 'اسم نوع الإجازة موجود بالفعل',
            'leave_type_name.string'         => 'اسم نوع الإجازة يجب أن يكون نص',
            'leave_type_name.max'            => 'اسم نوع الإجازة لا يجب أن يتجاوز 255 حرف',
            'requires_approval.boolean'      => 'حقل يتطلب موافقة يجب أن يكون قيمة منطقية (0 أو 1)',
            'is_paid_leave.boolean'          => 'حقل إجازة مدفوعة يجب أن يكون قيمة منطقية (0 أو 1)',
            'enable_leave_accrual.boolean'   => 'حقل تمكين استحقاق الإجازة يجب أن يكون قيمة منطقية (0 أو 1)',
            'is_carry.boolean'               => 'حقل الترحيل يجب أن يكون قيمة منطقية (0 أو 1)',
            'carry_limit.numeric'            => 'الحد المتجاوز للترحيل يجب أن يكون رقم',
            'carry_limit.min'                => 'الحد المتجاوز للترحيل يجب أن يكون أكبر من أو يساوي 0',
            'is_negative_quota.boolean'      => 'حقل رصيد الإجازة السالب يجب أن يكون قيمة منطقية (0 أو 1)',
            'negative_limit.numeric'         => 'رصيد الإجازة المستحق يجب أن يكون رقم',
            'negative_limit.min'             => 'رصيد الإجازة المستحق يجب أن يكون أكبر من أو يساوي 0',
            'is_quota.boolean'               => 'حقل تخصيص النسبة السنوية يجب أن يكون قيمة منطقية (0 أو 1)',
            'quota_assign.array'             => 'تخصيص النسبة السنوية يجب أن يكون مصفوفة',
            'quota_assign.max'               => 'تخصيص النسبة السنوية لا يجب أن يتجاوز 50 سنة',
            'quota_assign.*.numeric'         => 'قيم تخصيص النسبة السنوية يجب أن تكون أرقام',
            'quota_assign.*.min'             => 'قيم تخصيص النسبة السنوية يجب أن تكون أكبر من أو تساوي 0',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'leave_type_name'      => 'اسم نوع الإجازة',
            'requires_approval'    => 'يتطلب موافقة',
            'is_paid_leave'        => 'إجازة مدفوعة',
            'enable_leave_accrual' => 'تمكين استحقاق الإجازة',
            'is_carry'             => 'الترحيل',
            'carry_limit'          => 'الحد المتجاوز',
            'is_negative_quota'    => 'رصيد الإجازة السالب',
            'negative_limit'       => 'رصيد الإجازة المستحق',
            'is_quota'             => 'تخصيص النسبة السنوية',
            'quota_assign'         => 'تخصيص الأيام السنوية',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإنشاء أنواع إجازات جديدة'
            ], 403)
        );
    }

    protected function failedValidation(Validator $validator)
    {
        Log::warning('فشل إنشاء نوع إجازة', [
            'errors' => $validator->errors()->toArray(),
            'input'  => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل إنشاء نوع إجازة',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
