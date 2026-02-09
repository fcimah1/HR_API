<?php

declare(strict_types=1);

namespace App\Http\Requests\EndOfService;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * @OA\Schema(
 *     schema="UpdateEndOfServiceRequest",
 *     title="UpdateEndOfServiceRequest",
 *     description="طلب تحديث حساب نهاية الخدمة",
 *     @OA\Property(property="notes", type="string", example="ملاحظات إضافية"),
 *     @OA\Property(property="is_approved", type="boolean", example=true)
 * )
 */
class UpdateEndOfServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:1000',
            'is_approved' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'notes' => 'ملاحظات',
            'is_approved' => 'حالة الموافقة',
        ];
    }

    public function messages(): array
    {
        return [
            'notes.max' => 'الملاحظات يجب أن لا تتجاوز 1000 حرف',
            'is_approved.boolean' => 'حالة الموافقة يجب أن تكون صحيحة أو خاطئة',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
