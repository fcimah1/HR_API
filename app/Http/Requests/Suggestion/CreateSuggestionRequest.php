<?php

namespace App\Http\Requests\Suggestion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     schema="CreateSuggestionRequest",
 *     required={"title", "description"},
 *     @OA\Property(property="title", type="string", maxLength=255, example="اقتراح لتحسين بيئة العمل", description="عنوان الاقتراح"),
 *     @OA\Property(property="description", type="string", example="أقترح إضافة نباتات خضراء في المكتب", description="وصف تفصيلي للاقتراح"),
 *     @OA\Property(property="attachment", type="string", format="binary", description="ملف مرفق (jpeg, jpg, png, pdf) - الحد الأقصى 5MB")
 * )
 */
class CreateSuggestionRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'attachment' => [
                'nullable',
                'file',
                'mimes:jpeg,jpg,png,pdf',
                'max:5120', // 5MB
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'عنوان الاقتراح مطلوب',
            'title.max' => 'عنوان الاقتراح يجب ألا يتجاوز 255 حرف',
            'description.required' => 'وصف الاقتراح مطلوب',
            'attachment.file' => 'المرفق يجب أن يكون ملف',
            'attachment.mimes' => 'المرفق يجب أن يكون من نوع: jpeg, jpg, png, pdf',
            'attachment.max' => 'حجم المرفق يجب ألا يتجاوز 5 ميجابايت',
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
