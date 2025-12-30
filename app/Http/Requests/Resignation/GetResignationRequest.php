<?php

namespace App\Http\Requests\Resignation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Enums\NumericalStatusEnum;

class GetResignationRequest extends FormRequest
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
    protected function prepareForValidation(): void
    {
        // تحويل النصوص إلى أرقام (يطابق NumericalStatusEnum)
        // PENDING = 1, APPROVED = 2, REJECTED = 3
        $statusMap = [
            'pending' => NumericalStatusEnum::PENDING->value,
            'approved' => NumericalStatusEnum::APPROVED->value,
            'rejected' => NumericalStatusEnum::REJECTED->value,
        ];

        if ($this->has('status') && is_string($this->status) && !is_numeric($this->status)) {
            $status = strtolower($this->status);
            if (isset($statusMap[$status])) {
                $this->merge(['status' => $statusMap[$status]]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', new \App\Rules\CanRequestForEmployee()],
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1,2',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'employee_id.can_request_for_employee' => 'لا يمكن طلب رخصة للموظف المحدد',
            'status.in' => 'الحالة يجب أن تكون 0/1/2',
            'from_date.date' => 'تنسيق تاريخ البداية غير صحيح',
            'to_date.date' => 'تنسيق تاريخ النهاية غير صحيح',
            'to_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
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
