<?php

namespace App\Http\Requests\Transfer;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\Transfer;
use App\Enums\TransferTypeEnum;

class ApproveIntercompanyTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $transferId = $this->route('id');
        $transfer = Transfer::find($transferId);

        if ($transfer && $transfer->transfer_type !== TransferTypeEnum::INTERCOMPANY->value) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'فشل التحقق من البيانات',
                'errors' => [
                    'transfer_id' => ["لا يمكنك استخدام هذا الرابط لنوع نقل ({$transfer->transfer_type_text}). هذا الرابط مخصص للنقل بين الشركات فقط."]
                ]
            ], 422));
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:approve,reject',
            'remarks' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'action.required' => 'يجب تحديد الإجراء (موافقة أو رفض)',
            'action.in' => 'الإجراء يجب أن يكون approve (موافقة) أو reject (رفض)',
            'remarks.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف',
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
