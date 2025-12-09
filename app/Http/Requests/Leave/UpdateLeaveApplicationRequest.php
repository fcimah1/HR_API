<?php

namespace App\Http\Requests\Leave;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     schema="UpdateLeaveApplicationRequest",
 *     type="object",
 *     title="Update Leave Application Request",
 *     required={"from_date", "to_date", "reason", "remarks"},
 *     @OA\Property(property="from_date", type="string", format="date", description="From date"),
 *     @OA\Property(property="to_date", type="string", format="date", description="To date"),
 *     @OA\Property(property="reason", type="string", description="Reason for leave"),
 *     @OA\Property(property="remarks", type="string", description="Remarks")
 * )
 */

class UpdateLeaveApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('api')->check(); // تأكد من وجود مستخدم مصادق
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'from_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:today',
            'to_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:from_date',
            'reason' => 'sometimes|string',
            'remarks' => 'sometimes|nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'from_date.after_or_equal' => 'تاريخ بداية الإجازة يجب أن يكون اليوم أو بعده',
            'to_date.after_or_equal' => 'تاريخ نهاية الإجازة يجب أن يكون بعد أو يساوي تاريخ البداية',
            'reason.min' => 'سبب الإجازة يجب أن يكون على الأقل 10 أحرف',
            'reason.max' => 'سبب الإجازة لا يجب أن يتجاوز 1000 حرف',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تحديث طلب إجازة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
