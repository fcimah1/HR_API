<?php

declare(strict_types=1);

namespace App\Http\Requests\Trainer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="UpdateTrainerRequest",
 *     type="object",
 *     title="Update Trainer Request",
 *     description="طلب تحديث مدرب",
 *     @OA\Property(property="first_name", type="string", example="محمد", description="الاسم الأول"),
 *     @OA\Property(property="last_name", type="string", example="أحمد", description="اسم العائلة"),
 *     @OA\Property(property="contact_number", type="string", example="01234567890", description="رقم الهاتف"),
 *     @OA\Property(property="email", type="string", format="email", example="trainer@example.com", description="البريد الإلكتروني"),
 *     @OA\Property(property="expertise", type="string", example="PHP, Laravel", description="مجالات الخبرة"),
 *     @OA\Property(property="address", type="string", example="القاهرة", description="العنوان")
 * )
 */
class UpdateTrainerRequest extends FormRequest
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
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'expertise' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.string' => 'الاسم الأول يجب أن يكون نصاً',
            'first_name.max' => 'الاسم الأول يجب ألا يزيد عن 100 حرف',
            'last_name.string' => 'اسم العائلة يجب أن يكون نصاً',
            'last_name.max' => 'اسم العائلة يجب ألا يزيد عن 100 حرف',
            'contact_number.string' => 'رقم الهاتف يجب أن يكون نصاً',
            'contact_number.max' => 'رقم الهاتف يجب ألا يزيد عن 20 حرف',
            'email.email' => 'البريد الإلكتروني يجب أن يكون صالحاً',
            'email.max' => 'البريد الإلكتروني يجب ألا يزيد عن 150 حرف',
            'expertise.string' => 'مجالات الخبرة يجب أن تكون نصاً',
            'expertise.max' => 'مجالات الخبرة يجب ألا تزيد عن 500 حرف',
            'address.string' => 'العنوان يجب أن يكون نصاً',
            'address.max' => 'العنوان يجب ألا يزيد عن 500 حرف',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'الاسم الأول',
            'last_name' => 'اسم العائلة',
            'contact_number' => 'رقم الهاتف',
            'email' => 'البريد الإلكتروني',
            'expertise' => 'مجالات الخبرة',
            'address' => 'العنوان',
        ];
    }
}
