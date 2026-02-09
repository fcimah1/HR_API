<?php

namespace App\Http\Requests\Termination;

use App\Enums\NumericalStatusEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateTerminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notice_date' => 'required|date',
            'termination_date' => 'required|date',
            'reason' => 'required|string',
            'status' => [
                'required',
                'string',
                Rule::in(array_map(fn($case) => ucfirst(strtolower($case->name)), NumericalStatusEnum::cases()))
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'notice_date.required' => 'تاريخ الإشعار مطلوب',
            'notice_date.date' => 'تاريخ الإشعار يجب أن يكون تاريخًا',
            'termination_date.required' => 'تاريخ إنهاء الخدمة مطلوب',
            'termination_date.date' => 'تاريخ إنهاء الخدمة يجب أن يكون تاريخًا',
            'reason.required' => 'سبب إنهاء الخدمة مطلوب',
            'reason.string' => 'سبب إنهاء الخدمة يجب أن يكون نصًا',
            'status.required' => 'الحالة مطلوبة',
            'status.in' => 'الحالة غير صالحة، يجب أن تكون أحد القيم: [Pending, Approved, Rejected]',
        ];
    }

    public function attributes(): array
    {
        return [
            'notice_date' => 'تاريخ الإشعار',
            'termination_date' => 'تاريخ إنهاء الخدمة',
            'reason' => 'سبب إنهاء الخدمة',
            'status' => 'الحالة',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => false,
                'message' => 'ال فشل التحقق من البيانات ',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
