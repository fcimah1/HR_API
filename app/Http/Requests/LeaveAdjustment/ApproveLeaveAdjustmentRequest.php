<?php

namespace App\Http\Requests\LeaveAdjustment;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ApproveLeaveAdjustmentRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // No body required, but keep method for future fields
        return [];
    }

    /**
     * Custom validation messages (Arabic)
     *
     * @return array<string,string>
     */
    public function messages(): array
    {
        // Placeholder messages for future fields; currently no rules are defined.
        return [
            'remarks.string' => 'حقل الملاحظات يجب أن يكون نصًا.',
            'remarks.max' => 'حقل الملاحظات يجب ألا يزيد عن 1000 حرف.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
            Log::warning('فشل الموافقة على تسوية إجازة', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل الموافقة على تسوية إجازة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
