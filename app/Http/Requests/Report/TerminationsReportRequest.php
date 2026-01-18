<?php

namespace App\Http\Requests\Report;

use App\Enums\NumericalStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class TerminationsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statusValues = NumericalStatusEnum::valuesString();
        return [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'status' => 'nullable|integer|in:' . $statusValues,
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'تاريخ البداية',
            'end_date' => 'تاريخ النهاية',
            'status' => 'الحالة',
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
