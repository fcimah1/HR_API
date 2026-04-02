<?php

namespace App\Http\Requests\Announcement;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Department;
use App\Models\User;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     title="CreateAnnouncementRequest",
 *     description="طلب إنشاء إعلان جديد",
 *     required={"title", "start_date", "end_date", "summary", "description"}
 * )
 */
class CreateAnnouncementRequest extends FormRequest
{
    /**
     * @OA\Property(property="title", type="string", description="عنوان الإعلان", example="اجتماع عام"),
     * @OA\Property(property="start_date", type="string", format="date", description="تاريخ البدء", example="2024-02-01"),
     * @OA\Property(property="end_date", type="string", format="date", description="تاريخ الانتهاء", example="2024-02-10"),
     * @OA\Property(property="summary", type="string", description="ملخص الإعلان", example="ملخص سريع لموضوع الاجتماع"),
     * @OA\Property(property="description", type="string", description="وصف الإعلان بالتفصيل", example="تفاصيل الاجتماع وجدول الأعمال..."),
     * @OA\Property(property="department_id", type="string", description="معرفات الأقسام (مفصولة بفاصلة أو '0,all')", example="1,2,3"),
     * @OA\Property(property="audience_id", type="string", description="معرفات الموظفين (مفصولة بفاصلة أو '0,all')", example="4,5,6"),
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => 'nullable',
            'audience_id' => 'nullable',
            'title' => 'required|string|max:200',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'summary' => 'required|string',
            'description' => 'required|string',
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

            // التحقق من الأقسام
            if ($this->filled('department_id') && $this->department_id !== '0,all') {
                $deptIds = array_filter(explode(',', $this->department_id), fn($id) => is_numeric($id) && $id > 0);
                if (!empty($deptIds)) {
                    $validCount = Department::whereIn('department_id', $deptIds)
                        ->where('company_id', $effectiveCompanyId)
                        ->count();

                    if ($validCount !== count($deptIds)) {
                        $validator->errors()->add('department_id', 'بعض الأقسام المختارة لا تتبع لشركتك.');
                    }
                }
            }

            // التحقق من الموظفين
            if ($this->filled('audience_id') && $this->audience_id !== '0,all') {
                $empIds = array_filter(explode(',', $this->audience_id), fn($id) => is_numeric($id) && $id > 0);
                if (!empty($empIds)) {
                    $validCount = User::whereIn('user_id', $empIds)
                        ->where('company_id', $effectiveCompanyId)
                        ->count();

                    if ($validCount !== count($empIds)) {
                        $validator->errors()->add('audience_id', 'بعض الموظفين المختارين لا يتبعون لشركتك.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'title.required' => 'العنوان مطلوب.',
            'title.max' => 'العنوان لا يمكن أن يتجاوز 200 حرف.',
            'start_date.required' => 'تاريخ البدء مطلوب.',
            'start_date.date_format' => 'تاريخ البدء يجب أن يكون بصيغة Y-m-d.',
            'end_date.required' => 'تاريخ الانتهاء مطلوب.',
            'end_date.date_format' => 'تاريخ الانتهاء يجب أن يكون بصيغة Y-m-d.',
            'end_date.after_or_equal' => 'تاريخ الانتهاء يجب أن يكون بعد أو يساوي تاريخ البدء.',
            'summary.required' => 'الملخص مطلوب.',
            'description.required' => 'الوصف مطلوب.',
        ];
    }

    public function attributes(): array
    {
        return [
            'department_id' => 'القسم',
            'audience_id' => 'الجمهور',
            'title' => 'العنوان',
            'start_date' => 'تاريخ البدء',
            'end_date' => 'تاريخ الانتهاء',
            'summary' => 'الملخص',
            'description' => 'الوصف',
            'is_active' => 'الحالة',
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
