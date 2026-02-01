<?php

namespace App\Http\Requests\OfficeShift;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOfficeShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $rules = [
            'shift_name' => 'nullable|string|max:255',
            'hours_per_day' => 'nullable|integer|min:1|max:24',
            'late_allowance' => 'nullable|integer|min:0',
            'in_time_beginning' => 'nullable|string',
            'in_time_end' => 'nullable|string',
            'out_time_beginning' => 'nullable|string',
            'out_time_end' => 'nullable|string',
            'break_start' => 'nullable|string',
            'break_end' => 'nullable|string',
        ];

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($days as $day) {
            $rules[$day . '_in_time'] = 'nullable|string';
            $rules[$day . '_out_time'] = 'nullable|string';
            $rules[$day . '_lunch_break'] = 'nullable|string';
            $rules[$day . '_lunch_break_out'] = 'nullable|string';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'shift_name.required' => 'اسم الوردية مطلوب',
            'hours_per_day.required' => 'عدد ساعات العمل مطلوب',
            'hours_per_day.integer' => 'عدد ساعات العمل يجب أن يكون رقماً صحيحاً',
            'late_allowance.required' => 'السماح بالتاخير مطلوب',
            'late_allowance.integer' => 'السماح بالتاخير يجب أن يكون رقماً صحيحاً',
            'in_time_beginning.required' => 'وقت بداية العمل مطلوب',
            'in_time_end.required' => 'وقت نهاية العمل مطلوب',
            'out_time_beginning.required' => 'وقت بداية الخروج مطلوب',
            'out_time_end.required' => 'وقت نهاية الخروج مطلوب',
            'break_start.required' => 'وقت بداية الاستراحة مطلوب',
            'break_end.required' => 'وقت نهاية الاستراحة مطلوب',
            'monday_in_time.string' => 'وقت بداية الدخول يوم الاثنين يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'monday_out_time.string' => 'وقت نهاية الدخول يوم الاثنين يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'monday_lunch_break.string' => 'وقت بداية الاستراحة يوم الاثنين يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'monday_lunch_break_out.string' => 'وقت نهاية الاستراحة يوم الاثنين يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'tuesday_in_time.string' => 'وقت بداية الدخول يوم الثلاثاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'tuesday_out_time.string' => 'وقت نهاية الدخول يوم الثلاثاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'tuesday_lunch_break.string' => 'وقت بداية الاستراحة يوم الثلاثاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'tuesday_lunch_break_out.string' => 'وقت نهاية الاستراحة يوم الثلاثاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'wednesday_in_time.string' => 'وقت بداية الدخول يوم الاربعاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'wednesday_out_time.string' => 'وقت نهاية الدخول يوم الاربعاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'wednesday_lunch_break.string' => 'وقت بداية الاستراحة يوم الاربعاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'wednesday_lunch_break_out.string' => 'وقت نهاية الاستراحة يوم الاربعاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'thursday_in_time.string' => 'وقت بداية الدخول يوم الخميس يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'thursday_out_time.string' => 'وقت نهاية الدخول يوم الخميس يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'thursday_lunch_break.string' => 'وقت بداية الاستراحة يوم الخميس يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'thursday_lunch_break_out.string' => 'وقت نهاية الاستراحة يوم الخميس يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'friday_in_time.string' => 'وقت بداية الدخول يوم الجمعة يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'friday_out_time.string' => 'وقت نهاية الدخول يوم الجمعة يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'friday_lunch_break.string' => 'وقت بداية الاستراحة يوم الجمعة يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'friday_lunch_break_out.string' => 'وقت نهاية الاستراحة يوم الجمعة يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'saturday_in_time.string' => 'وقت بداية الدخول يوم السبت يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'saturday_out_time.string' => 'وقت نهاية الدخول يوم السبت يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'saturday_lunch_break.string' => 'وقت بداية الاستراحة يوم السبت يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'saturday_lunch_break_out.string' => 'وقت نهاية الاستراحة يوم السبت يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'sunday_in_time.string' => 'وقت بداية الدخول يوم الاحد يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'sunday_out_time.string' => 'وقت نهاية الدخول يوم الاحد يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'sunday_lunch_break.string' => 'وقت بداية الاستراحة يوم الاحد يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'sunday_lunch_break_out.string' => 'وقت نهاية الاستراحة يوم الاحد يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
