<?php

namespace App\Http\Requests\Report;

use App\Enums\NumericalStatusEnum;
use App\Enums\TransferTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class TransfersReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statusValues = NumericalStatusEnum::valuesString();
        $transferTypeValues = TransferTypeEnum::valuesString() . ',all';
        return [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'status' => 'nullable|integer|in:' . $statusValues,
            'transfer_type' => 'nullable|string|in:' . $transferTypeValues,
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'تاريخ البداية',
            'end_date' => 'تاريخ النهاية',
            'status' => 'الحالة',
            'transfer_type' => 'نوع التحويل',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'تاريخ البداية مطلوب',
            'end_date.required' => 'تاريخ النهاية مطلوب',
            'status.in' => 'الحالة غير صالحة',
            'transfer_type.in' => 'نوع التحويل غير صالح',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
