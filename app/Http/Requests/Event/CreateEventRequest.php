<?php

declare(strict_types=1);

namespace App\Http\Requests\Event;

use App\Services\SimplePermissionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionService = app(SimplePermissionService::class);
        $user = Auth::user();
        $companyId = $permissionService->getEffectiveCompanyId($user);

        $subordinateIds = [];
        if (!$permissionService->isCompanyOwner($user)) {
            $subordinates = $permissionService->getEmployeesByHierarchy(
                $user->user_id,
                $companyId,
                true // Include self
            );
            $subordinateIds = array_column($subordinates, 'user_id');
        }

        return [
            'event_title' => 'required|string|max:255',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => [
                'integer',
                function ($attribute, $value, $fail) use ($companyId, $subordinateIds, $user, $permissionService) {
                    // Check if exists in company
                    $exists = \Illuminate\Support\Facades\DB::table('ci_erp_users')
                        ->where('user_id', $value)
                        ->where('company_id', $companyId)
                        ->exists();

                    if (!$exists) {
                        $fail('معرف الموظف غير موجود في الشركة.');
                        return;
                    }

                    // Check if subordinate (if not owner)
                    if (!$permissionService->isCompanyOwner($user) && !in_array($value, $subordinateIds)) {
                        $fail('لا تملك الصلاحية لإضافة هذا الموظف للحدث (يجب أن يكون من التابعين لك).');
                    }
                },
            ],
            'event_date' => 'required|date_format:Y-m-d',
            'event_time' => 'required|string', // format like 02:15 or 14:00
            'event_note' => 'required|string',
            'event_color' => 'nullable|string|max:20',
            'is_show_calendar' => 'nullable|integer|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'event_title.required' => 'يرجى إدخال عنوان الحدث',
            'event_title.string' => 'يجب أن يكون عنوان الحدث نصًا',
            'event_title.max' => 'يجب أن لا يتجاوز عنوان الحدث 255 حرفًا',
            'employee_ids.array' => 'يجب أن تكون معرفات الموظفين مصفوفة',
            'employee_ids.*.integer' => 'يجب أن تكون معرفات الموظفين أعدادًا صحيحة',
            'employee_ids.*.exists' => 'معرف الموظف غير موجود',
            'event_date.required' => 'يرجى اختيار تاريخ الحدث',
            'event_date.date_format' => 'صيغة التاريخ غير صحيحة، يجب أن تكون YYYY-MM-DD',
            'event_time.required' => 'يرجى تحديد وقت الحدث',
            'event_time.string' => 'يجب أن يكون وقت الحدث نصًا',
            'event_note.required' => 'يرجى إدخال ملاحظات الحدث',
            'event_note.string' => 'يجب أن تكون ملاحظات الحدث نصًا',
            'event_color.string' => 'يجب أن يكون لون الحدث نصًا',
            'event_color.max' => 'يجب أن لا يتجاوز لون الحدث 20 حرفًا',
            'is_show_calendar.integer' => 'يجب أن تكون حالة عرض التقويم عددًا صحيحًا',
            'is_show_calendar.in' => 'يجب أن تكون حالة عرض التقويم 0 أو 1',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
