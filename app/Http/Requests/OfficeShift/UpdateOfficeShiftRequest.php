<?php

namespace App\Http\Requests\OfficeShift;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Services\SimplePermissionService;

class UpdateOfficeShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $permissionService = app(SimplePermissionService::class);
        $companyId = $permissionService->getEffectiveCompanyId(Auth::user());
        $id = $this->route('id'); // Get ID from route

        $rules = [
            'shift_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ci_office_shifts', 'shift_name')->where('company_id', $companyId)->ignore($id, 'office_shift_id'),
            ],
            'hours_per_day' => 'required|integer|min:1|max:24',
            'late_allowance' => 'required|integer|min:0',
            'in_time_beginning' => 'required|date_format:H:i',
            'in_time_end' => 'required|date_format:H:i',
            'out_time_beginning' => 'required|date_format:H:i',
            'out_time_end' => 'required|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
        ];

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($days as $day) {
            $rules[$day . '_in_time'] = 'required|date_format:H:i';
            $rules[$day . '_out_time'] = 'required|date_format:H:i';
            $rules[$day . '_lunch_break'] = 'required|date_format:H:i';
            $rules[$day . '_lunch_break_out'] = 'required|date_format:H:i';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'shift_name.required' => 'اسم الوردية مطلوب',
            'shift_name.unique' => 'اسم الوردية موجود بالفعل',
            'shift_name.max' => 'اسم الوردية يجب أن لا يتجاوز 255 حرف',
            'hours_per_day.required' => 'عدد ساعات العمل مطلوب',
            'hours_per_day.max' => 'عدد ساعات العمل يجب أن لا يتجاوز 24 ساعة',
            'late_allowance.required' => 'السماح بالتاخير مطلوب',
            'in_time_beginning.required' => 'وقت بداية العمل مطلوب',
            'in_time_end.required' => 'وقت نهاية العمل مطلوب',
            'out_time_beginning.required' => 'وقت بداية الخروج مطلوب',
            'out_time_end.required' => 'وقت نهاية الخروج مطلوب',
            'break_start.required' => 'وقت بداية الاستراحة مطلوب',
            'break_end.required' => 'وقت نهاية الاستراحة مطلوب',
            'monday_in_time.date_format' => 'وقت بداية الدخول يوم الاثنين يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'monday_in_time.required' => 'وقت بداية الدخول يوم الاثنين مطلوب',
            'monday_out_time.date_format' => 'وقت نهاية الدخول يوم الاثنين يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'monday_out_time.required' => 'وقت نهاية الدخول يوم الاثنين مطلوب',
            'monday_lunch_break.date_format' => 'وقت بداية الاستراحة يوم الاثنين يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'monday_lunch_break.required' => 'وقت بداية الاستراحة يوم الاثنين مطلوب',
            'monday_lunch_break_out.date_format' => 'وقت نهاية الاستراحة يوم الاثنين يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'monday_lunch_break_out.required' => 'وقت نهاية الاستراحة يوم الاثنين مطلوب',
            'tuesday_in_time.date_format' => 'وقت بداية الدخول يوم الثلاثاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'tuesday_in_time.required' => 'وقت بداية الدخول يوم الثلاثاء مطلوب',
            'tuesday_out_time.date_format' => 'وقت نهاية الدخول يوم الثلاثاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'tuesday_out_time.required' => 'وقت نهاية الدخول يوم الثلاثاء مطلوب',
            'tuesday_lunch_break.date_format' => 'وقت بداية الاستراحة يوم الثلاثاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'tuesday_lunch_break.required' => 'وقت بداية الاستراحة يوم الثلاثاء مطلوب',
            'tuesday_lunch_break_out.date_format' => 'وقت نهاية الاستراحة يوم الثلاثاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'tuesday_lunch_break_out.required' => 'وقت نهاية الاستراحة يوم الثلاثاء مطلوب',
            'wednesday_in_time.date_format' => 'وقت بداية الدخول يوم الاربعاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'wednesday_in_time.required' => 'وقت بداية الدخول يوم الاربعاء مطلوب',
            'wednesday_out_time.date_format' => 'وقت نهاية الدخول يوم الاربعاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'wednesday_out_time.required' => 'وقت نهاية الدخول يوم الاربعاء مطلوب',
            'wednesday_lunch_break.date_format' => 'وقت بداية الاستراحة يوم الاربعاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'wednesday_lunch_break.required' => 'وقت بداية الاستراحة يوم الاربعاء مطلوب',
            'wednesday_lunch_break_out.date_format' => 'وقت نهاية الاستراحة يوم الاربعاء يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'wednesday_lunch_break_out.required' => 'وقت نهاية الاستراحة يوم الاربعاء مطلوب',
            'thursday_in_time.date_format' => 'وقت بداية الدخول يوم الخميس يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'thursday_in_time.required' => 'وقت بداية الدخول يوم الخميس مطلوب',
            'thursday_out_time.date_format' => 'وقت نهاية الدخول يوم الخميس يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'thursday_out_time.required' => 'وقت نهاية الدخول يوم الخميس مطلوب',
            'thursday_lunch_break.date_format' => 'وقت بداية الاستراحة يوم الخميس يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'thursday_lunch_break.required' => 'وقت بداية الاستراحة يوم الخميس مطلوب',
            'thursday_lunch_break_out.date_format' => 'وقت نهاية الاستراحة يوم الخميس يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'thursday_lunch_break_out.required' => 'وقت نهاية الاستراحة يوم الخميس مطلوب',
            'friday_in_time.date_format' => 'وقت بداية الدخول يوم الجمعة يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'friday_in_time.required' => 'وقت بداية الدخول يوم الجمعة مطلوب',
            'friday_out_time.date_format' => 'وقت نهاية الدخول يوم الجمعة يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'friday_out_time.required' => 'وقت نهاية الدخول يوم الجمعة مطلوب',
            'friday_lunch_break.date_format' => 'وقت بداية الاستراحة يوم الجمعة يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'friday_lunch_break.required' => 'وقت بداية الاستراحة يوم الجمعة مطلوب',
            'friday_lunch_break_out.date_format' => 'وقت نهاية الاستراحة يوم الجمعة يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'friday_lunch_break_out.required' => 'وقت نهاية الاستراحة يوم الجمعة مطلوب',
            'saturday_in_time.date_format' => 'وقت بداية الدخول يوم السبت يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'saturday_in_time.required' => 'وقت بداية الدخول يوم السبت مطلوب',
            'saturday_out_time.date_format' => 'وقت نهاية الدخول يوم السبت يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'saturday_out_time.required' => 'وقت نهاية الدخول يوم السبت مطلوب',
            'saturday_lunch_break.date_format' => 'وقت بداية الاستراحة يوم السبت يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'saturday_lunch_break.required' => 'وقت بداية الاستراحة يوم السبت مطلوب',
            'saturday_lunch_break_out.date_format' => 'وقت نهاية الاستراحة يوم السبت يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'saturday_lunch_break_out.required' => 'وقت نهاية الاستراحة يوم السبت مطلوب',
            'sunday_in_time.date_format' => 'وقت بداية الدخول يوم الأحد يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'sunday_in_time.required' => 'وقت بداية الدخول يوم الأحد مطلوب',
            'sunday_out_time.date_format' => 'وقت نهاية الدخول يوم الأحد يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'sunday_out_time.required' => 'وقت نهاية الدخول يوم الأحد مطلوب',
            'sunday_lunch_break.date_format' => 'وقت بداية الاستراحة يوم الأحد يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'sunday_lunch_break.required' => 'وقت بداية الاستراحة يوم الأحد مطلوب',
            'sunday_lunch_break_out.date_format' => 'وقت نهاية الاستراحة يوم الأحد يجب أن يكون وقت بطريقة صحيحه اعمتادا على 24 ساعه',
            'sunday_lunch_break_out.required' => 'وقت نهاية الاستراحة يوم الأحد مطلوب',
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
