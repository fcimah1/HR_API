<?php

declare(strict_types=1);

namespace App\Http\Requests\Event;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_title' => 'required|string|max:255',
            'event_date' => 'required|date_format:Y-m-d',
            'event_time' => 'required|string',
            'event_note' => 'required|string',
            'event_color' => 'nullable|string|max:20',
            'is_show_calendar' => 'nullable|integer|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'event_title.required' => 'يرجى إدخال عنوان الحدث',
            'event_title.string' => 'يجب أن يكون عنوان الحدث نصًا',
            'event_title.max' => 'يجب أن لا يتجاوز عنوان الحدث 255 حرفًا',
            'event_date.required' => 'يرجى اختيار تاريخ الحدث',
            'event_date.date_format' => 'صيغة التاريخ غير صحيحة، يجب أن تكون YYYY-MM-DD',
            'event_time.required' => 'يرجى تحديد وقت الحدث',
            'event_time.string' => 'يجب أن يكون وقت الحدث نصًا',
            'event_note.required' => 'يرجى إدخال ملاحظات الحدث',
            'event_note.string' => 'يجب أن تكون ملاحظات الحدث نصًا',
            'event_color.required' => 'يرجى إدخال لون الحدث',
            'event_color.string' => 'يجب أن يكون لون الحدث نصًا',
            'event_color.max' => 'يجب أن لا يتجاوز لون الحدث 20 حرفًا',
            'is_show_calendar.integer' => 'يجب أن تكون حالة عرض التقويم عددًا صحيحًا',
            'is_show_calendar.in' => 'يجب أن تكون حالة عرض التقويم 0 أو 1',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
