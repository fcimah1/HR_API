<?php

namespace App\Http\Requests\Polls;

use Illuminate\Foundation\Http\FormRequest;

class VotePollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'votes' => 'required|array|min:1',
            'votes.*.question_id' => 'required|integer',
            'votes.*.answer' => 'required|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'votes.*.question_id.required' => 'السؤال مطلوب',
            'votes.*.answer.required' => 'الإجابة مطلوبة',
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
