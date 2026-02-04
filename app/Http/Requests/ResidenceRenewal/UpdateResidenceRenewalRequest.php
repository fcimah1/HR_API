<?php

namespace App\Http\Requests\ResidenceRenewal;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateResidenceRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'work_permit_fee' => 'nullable|numeric|min:0',
            'residence_renewal_fees' => 'nullable|numeric|min:0',
            'penalty_amount' => 'nullable|numeric|min:0',
            'current_residence_expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'nullable|string',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => false,
                'message' => 'البيانات غير صالحة',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
