<?php

namespace App\Http\Requests\Announcement;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * @OA\Schema(
 *     title="UpdateAnnouncementRequest",
 *     description="طلب تحديث إعلان موجود"
 * )
 */
class UpdateAnnouncementRequest extends FormRequest
{
    /**
     * @OA\Property(property="title", type="string", description="عنوان الإعلان", example="اجتماع عام - محدث"),
     * @OA\Property(property="start_date", type="string", format="date", description="تاريخ البدء", example="2024-02-01"),
     * @OA\Property(property="end_date", type="string", format="date", description="تاريخ الانتهاء", example="2024-02-10"),
     * @OA\Property(property="summary", type="string", description="ملخص الإعلان", example="ملخص محدث"),
     * @OA\Property(property="description", type="string", description="وصف الإعلان بالتفصيل", example="تفاصيل محدثة..."),
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:200',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'summary' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'العنوان',
            'start_date' => 'تاريخ البدء',
            'end_date' => 'تاريخ الانتهاء',
            'summary' => 'الملخص',
            'description' => 'الوصف',
            'is_active' => 'الحالة',
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'العنوان لا يمكن أن يتجاوز 200 حرف.',
            'start_date.date_format' => 'تاريخ البدء يجب أن يكون بصيغة Y-m-d.',
            'end_date.date_format' => 'تاريخ الانتهاء يجب أن يكون بصيغة Y-m-d.',
            'end_date.after_or_equal' => 'تاريخ الانتهاء يجب أن يكون بعد أو يساوي تاريخ البدء.',
            'is_active.boolean' => 'الحالة يجب أن تكون true أو false.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'فشل التحقق من البيانات.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
