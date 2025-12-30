<?php

namespace App\Http\Requests\Suggestion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class GetSuggestionRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', new \App\Rules\CanRequestComplaintForEmployee()],
            'search' => 'nullable|string|max:255',
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
            'employee_id.can_request_complaint_for_employee' => 'الموظف غير موجود أو لا ينتمي لنفس الشركة.',
            'from_date.date' => 'تنسيق تاريخ البداية غير صحيح',
            'to_date.date' => 'تنسيق تاريخ النهاية غير صحيح',
            'to_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
            'page.integer' => 'رقم الصفحة يجب أن يكون رقمًا صحيحًا',
            'per_page.integer' => 'عدد العناصر في الصفحة يجب أن يكون رقمًا صحيحًا',
            'per_page.max' => 'عدد العناصر في الصفحة يجب ألا يتجاوز 100',
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
