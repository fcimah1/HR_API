<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class EmployeesByBranchReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => 'nullable|integer', // null or 'all' logic handled in service/repo if needed, usually passed as ID or null
            'status' => 'nullable|string|in:active,inactive,left,all',
        ];
    }

    public function attributes(): array
    {
        return [
            'branch_id' => 'الفرع',
            'status' => 'الحالة',
        ];
    }

    public function messages()
    {
        return [
            'branch_id.required' => 'يجب اختيار الفرع',
            'status.required' => 'يجب اختيار الحالة',
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
