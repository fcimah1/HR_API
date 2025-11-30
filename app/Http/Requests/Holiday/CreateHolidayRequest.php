<?php

namespace App\Http\Requests\Holiday;

use Illuminate\Foundation\Http\FormRequest;

class CreateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'is_publish' => 'nullable|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'event_name.required' => 'يرجى إدخال اسم العطلة',
            'start_date.required' => 'يرجى إدخال تاريخ البداية',
            'end_date.required' => 'يرجى إدخال تاريخ النهاية',
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
        ];
    }
}
