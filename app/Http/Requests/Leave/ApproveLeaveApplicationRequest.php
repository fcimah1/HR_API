<?php

namespace App\Http\Requests\Leave;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Schema(
 *     schema="ApproveLeaveApplicationRequest",
 *     type="object",
 *     title="Approve Leave Application Request",
 *     required={"action", "remarks"},
 *     @OA\Property(property="action", type="string", description="Action to take (approve/reject)"),
 *     @OA\Property(property="remarks", type="string", description="Remarks for approval"),
 *     @OA\Property(property="include_holidays", type="boolean", description="Include holidays in the leave application"),
 *     @OA\Property(property="is_deducted", type="boolean", description="Is the leave application deducted from the employee's balance")
 * )
 */

class ApproveLeaveApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject',
            'remarks' => 'required|string|max:1000',
            'include_holidays' => 'required|boolean',
            'is_deducted' => 'required|boolean',
        ];
    }

    /**
     * Custom validation messages (Arabic)
     *
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'حقل الإجراء مطلوب',
            'action.in' => 'حقل الإجراء يجب أن يكون approve أو reject',
            'remarks.string' => 'حقل الملاحظات يجب أن يكون نصًا.',
            'remarks.max' => 'حقل الملاحظات يجب ألا يزيد عن 1000 حرف.',
            'include_holidays.required' => 'حقل إدراج أيام العطل مطلوب',
            'include_holidays.boolean' => 'حقل إدراج أيام العطل يجب أن يكون boolean.',
            'is_deducted.required' => 'حقل إدراك إجازة مطلوب',
            'is_deducted.boolean' => 'حقل إدراك إجازة يجب أن يكون boolean.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        Log::warning('فشل الموافقة على طلب إجازة', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل الموافقة على طلب إجازة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
