<?php

namespace App\Http\Requests\Travel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Schema(
 *     schema="UpdateTravelStatusRequest",
 *     type="object",
 *     title="Update Travel Status Request",
 *     required={"action"},
 *     @OA\Property(property="action", type="string", enum={"approve", "reject"}, description="Action to perform on the travel request")
 * )
 */
class UpdateTravelStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in controller / permission service
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'حقل الحالة مطلوب',
            'action.in' => 'القيمة غير صالحة. يجب أن تكون approve أو reject',
        ];
    }

    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        Log::warning('فشل تحديث حالة طلب السفر', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تحديث حالة طلب السفر',
            'errors' => $validator->errors(),
        ], 422));
    }
}
