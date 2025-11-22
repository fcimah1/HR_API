<?php

namespace App\Http\Requests\LeaveAdjustment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     schema="CreateLeaveSettlementRequest",
 *     type="object",
 *     title="Create Leave Settlement Request",
 *     required={"leave_type_id", "hours_to_settle", "settlement_type"},
 *     @OA\Property(property="leave_type_id", type="integer", description="Leave type ID"),
 *     @OA\Property(property="hours_to_settle", type="number", format="float", description="Hours to settle"),
 *     @OA\Property(property="settlement_type", type="string", description="Settlement type")
 * )
 */

class CreateLeaveSettlementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Assuming authorization logic is handled by middleware or in the controller
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'leave_type_id' => 'required|integer|exists:ci_constants,constants_id', // Assuming ci_constants is the table for leave types
            'hours_to_settle' => 'required|numeric|min:0.01',
            'settlement_type' => 'required|string|in:encashment,take_leave',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'يجب تحديد نوع الإجازة.',
            'leave_type_id.integer' => 'يجب أن يكون معرف نوع الإجازة رقماً صحيحاً.',
            'leave_type_id.exists' => 'نوع الإجازة المحدد غير موجود.',
            'hours_to_settle.required' => 'يجب تحديد عدد الساعات المراد تسويتها.',
            'hours_to_settle.numeric' => 'يجب أن تكون الساعات المراد تسويتها رقماً.',
            'hours_to_settle.min' => 'يجب أن تكون الساعات المراد تسويتها أكبر من صفر.',
            'settlement_type.required' => 'يجب تحديد نوع التسوية (صرف نقدي أو أخذ إجازة).',
            'settlement_type.in' => 'نوع التسوية غير صالح. يجب أن يكون "encashment" أو "take_leave".',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422));
    }
}