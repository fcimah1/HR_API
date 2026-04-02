<?php

declare(strict_types=1);

namespace App\Http\Requests\Meeting;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * @OA\Schema(
 *     schema="UpdateMeetingRequest",
 *     title="UpdateMeetingRequest",
 *     description="طلب تحديث اجتماع",
 *     @OA\Property(property="meeting_title", type="string", example="اجتماع مجلس الإدارة"),
 *     @OA\Property(property="meeting_date", type="string", format="date", example="2023-10-25"),
 *     @OA\Property(property="meeting_time", type="string", example="10:00"),
 *     @OA\Property(property="meeting_room", type="string", example="قاعة الاجتماعات الرئيسية"),
 *     @OA\Property(property="meeting_note", type="string", example="ملاحظات حول الاجتماع"),
 *     @OA\Property(property="meeting_color", type="string", example="#ff0000")
 * )
 */
class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_title' => 'required|string|max:255',
            'meeting_date' => 'required|string|max:255|date_format:Y-m-d|after_or_equal:today',
            'meeting_time' => 'required|string|max:255',
            'meeting_room' => 'required|string|max:255',
            'meeting_note' => 'required|string',
            'meeting_color' => 'required|string|max:200',
        ];
    }

    public function attributes(): array
    {
        return [
            'meeting_title' => 'عنوان الاجتماع',
            'meeting_date' => 'تاريخ الاجتماع',
            'meeting_time' => 'وقت الاجتماع',
            'meeting_room' => 'غرفة الاجتماع',
            'meeting_note' => 'ملحوظة',
            'meeting_color' => 'اللون',
        ];
    }

    public function messages(): array
    {
        return [
            'meeting_title.required' => 'عنوان الاجتماع مطلوب',
            'meeting_title.string' => 'عنوان الاجتماع يجب أن يكون نصًا',
            'meeting_title.max' => 'عنوان الاجتماع يجب أن لا يتجاوز 255 حرفًا',
            'meeting_date.required' => 'تاريخ الاجتماع مطلوب',
            'meeting_date.string' => 'تاريخ الاجتماع يجب أن يكون نصًا',
            'meeting_date.max' => 'تاريخ الاجتماع يجب أن لا يتجاوز 255 حرفًا',
            'meeting_date.date_format' => 'تاريخ الاجتماع يجب أن يكون بصيغة Y-m-d',
            'meeting_date.after_or_equal' => 'تاريخ الاجتماع يجب أن يكون بعد أو يساوي تاريخ اليوم',
            'meeting_time.required' => 'وقت الاجتماع مطلوب',
            'meeting_time.string' => 'وقت الاجتماع يجب أن يكون نصًا',
            'meeting_time.max' => 'وقت الاجتماع يجب أن لا يتجاوز 255 حرفًا',
            'meeting_room.required' => 'غرفة الاجتماع مطلوبة',
            'meeting_room.string' => 'غرفة الاجتماع يجب أن تكون نصًا',
            'meeting_room.max' => 'غرفة الاجتماع يجب أن لا تتجاوز 255 حرفًا',
            'meeting_note.required' => 'الملحوظة مطلوبة',
            'meeting_note.string' => 'الملحوظة يجب أن تكون نصًا',
            'meeting_color.required' => 'اللون مطلوب',
            'meeting_color.string' => 'اللون يجب أن يكون نصًا',
            'meeting_color.max' => 'اللون يجب أن لا يتجاوز   200 حرفًا',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
