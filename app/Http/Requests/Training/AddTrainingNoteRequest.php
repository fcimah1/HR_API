<?php

declare(strict_types=1);

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="AddTrainingNoteRequest",
 *     type="object",
 *     title="Add Training Note Request",
 *     description="طلب إضافة ملاحظة للتدريب",
 *     required={"note"},
 *     @OA\Property(
 *         property="note",
 *         type="string",
 *         example="ملاحظة حول أداء المتدربين",
 *         description="نص الملاحظة"
 *     )
 * )
 */
class AddTrainingNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'note.required' => 'الملاحظة مطلوبة',
            'note.string' => 'الملاحظة يجب أن تكون نصاً',
            'note.max' => 'الملاحظة يجب ألا تزيد عن 2000 حرف',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'note' => 'الملاحظة',
        ];
    }
}
