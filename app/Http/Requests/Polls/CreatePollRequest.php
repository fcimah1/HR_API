<?php

namespace App\Http\Requests\Polls;

use Illuminate\Foundation\Http\FormRequest;

class CreatePollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'poll_title' => 'required|string|max:255',
            'poll_start_date' => 'required|date',
            'poll_end_date' => 'required|date|after_or_equal:poll_start_date',
            'is_active' => 'nullable|boolean',
            'questions' => 'required|array|min:1',
            'questions.*.poll_question' => 'required|string',
            'questions.*.poll_answer1' => 'required|string',
            'questions.*.poll_answer2' => 'nullable|string',
            'questions.*.poll_answer3' => 'nullable|string',
            'questions.*.poll_answer4' => 'nullable|string',
            'questions.*.poll_answer5' => 'nullable|string',
            'questions.*.notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'poll_title.required' => 'العنوان مطلوب',
            'poll_start_date.required' => 'تاريخ البدء مطلوب',
            'poll_end_date.required' => 'تاريخ الانتهاء مطلوب',
            'questions.*.poll_question.required' => 'السؤال مطلوب',
            'questions.*.poll_answer1.required' => 'الإجابة 1 مطلوبة',
            'questions.*.poll_answer2.required' => 'الإجابة 2 مطلوبة',
        ];
    }

    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException(
            response()->json([
                'success' => false,
                'message' => 'فشل التحقق من البيانات',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
