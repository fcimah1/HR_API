<?php

declare(strict_types=1);

namespace App\Http\Requests\Meeting;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     schema="CreateMeetingRequest",
 *     title="CreateMeetingRequest",
 *     description="طلب إنشاء اجتماع جديد",
 *     required={"meeting_title", "meeting_date", "meeting_time", "meeting_room"},
 *     @OA\Property(property="meeting_title", type="string", example="اجتماع مجلس الإدارة"),
 *     @OA\Property(property="meeting_date", type="string", format="date", example="2023-10-25"),
 *     @OA\Property(property="meeting_time", type="string", example="10:00"),
 *     @OA\Property(property="meeting_room", type="string", example="قاعة الاجتماعات الرئيسية"),
 *     @OA\Property(property="employee_id", type="string", example="1,2,3"),
 *     @OA\Property(property="meeting_note", type="string", example="ملاحظات حول الاجتماع"),
 *     @OA\Property(property="meeting_color", type="string", example="#ff0000")
 * )
 */
class CreateMeetingRequest extends FormRequest
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
            'employee_id' => 'nullable|string',
            'meeting_note' => 'required|string',
            'meeting_color' => 'required|string|max:200',
        ];
    }

    /**
     * التحقق من أن الأقسام والموظفين يتبعون لنفس الشركة
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $permissionService = resolve(\App\Services\SimplePermissionService::class);
            $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

            // التحقق من الموظفين
            if ($this->filled('employee_id') && $this->employee_id !== '') {
                $empIds = array_filter(explode(',', (string)$this->employee_id), fn($id) => is_numeric($id) && $id > 0);
                if (!empty($empIds)) {
                    $validCount = User::whereIn('user_id', $empIds)
                        ->where('company_id', $effectiveCompanyId)
                        ->count();

                    if ($validCount !== count($empIds)) {
                        $validator->errors()->add('employee_id', 'بعض الموظفين المختارين لا يتبعون لشركتك.');
                    }
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'meeting_title' => 'عنوان الاجتماع',
            'meeting_date' => 'تاريخ الاجتماع',
            'meeting_time' => 'وقت الاجتماع',
            'meeting_room' => 'غرفة الاجتماع',
            'employee_id' => 'الموظفين',
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
            'employee_id.string' => 'الموظفين يجب أن يكونوا نصًا',
            'meeting_note.required' => 'الملحوظة مطلوبة',
            'meeting_note.string' => 'الملحوظة يجب أن تكون نصًا',
            'meeting_color.required' => 'اللون مطلوب',
            'meeting_color.string' => 'اللون يجب أن يكون نصًا',
            'meeting_color.max' => 'اللون يجب أن لا يتجاوز 200 حرفًا',
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
