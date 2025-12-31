<?php

namespace App\Http\Requests\Travel;

use App\Enums\TravelModeEnum;
use App\Models\Travel;
use App\Services\SimplePermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="CreateTravelRequest",
 *     type="object",
 *     title="Create Travel Request",
 *     required={"start_date", "end_date", "visit_purpose", "visit_place", "travel_mode", "arrangement_type"},
 *     @OA\Property(property="employee_id", type="integer", description="Employee ID (optional, for admin/company)"),
 *     @OA\Property(property="start_date", type="string", format="date", description="تاريخ البداية"),
 *     @OA\Property(property="end_date", type="string", format="date", description="تاريخ النهاية"),
 *     @OA\Property(property="visit_purpose", type="string", description="غرض الزيارة"),
 *     @OA\Property(property="visit_place", type="string", description="مكان الزيارة"),
 *     @OA\Property(property="travel_mode", type="integer", description="وضع السفر (1-5)"),
 *     @OA\Property(property="arrangement_type", type="integer", description="نوع ترتيب السفر"),
 *     @OA\Property(property="description", type="string", description="الوصف"),
 *     @OA\Property(property="associated_goals", type="string", description="الأهداف المرتبطة"),
 *     @OA\Property(property="remarks", type="string", description="ملاحظات")
 * )
 */
class CreateTravelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check(); // Authorization is handled in Controller/Service
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'nullable',
                'integer',
                'exists:ci_erp_users,user_id',
                new \App\Rules\CanRequestForEmployee(),
            ],
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'visit_purpose' => 'required|string|max:255',
            'visit_place' => 'required|string|max:255',
            'travel_mode' => ['required', 'integer', Rule::in(TravelModeEnum::cases())],
            // 'arrangement_type' => 'required|integer|in:' . implode(',', Travel::getArrangementTypes()),
            'arrangement_type' => ['required', 'integer', Rule::in(Travel::getArrangementTypes(app(SimplePermissionService::class)->getEffectiveCompanyId(Auth::user())))],
            'description' => 'nullable|string',
            'associated_goals' => 'nullable|string',
            'remarks' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.date' => 'تاريخ البداية يجب أن يكون تاريخاً صالحاً.',
            'start_date.after_or_equal' => 'تاريخ البداية يجب أن يكون اليوم أو بعده.',
            'end_date.date' => 'تاريخ النهاية يجب أن يكون تاريخاً صالحاً.',
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون في نفس تاريخ البداية أو بعده.',
            'visit_purpose.string' => 'غرض الزيارة يجب أن يكون نصاً.',
            'visit_purpose.max' => 'غرض الزيارة يجب أن يكون 255 حرفاً أو أقل.',
            'visit_place.string' => 'مكان الزيارة يجب أن يكون نصاً.',
            'visit_place.max' => 'مكان الزيارة يجب أن يكون 255 حرفاً أو أقل.',
            'travel_mode.required' => 'وضع السفر مطلوب.',
            'travel_mode.integer' => 'وضع السفر يجب أن يكون رقماً.',
            'travel_mode.in' => 'يجب أن يكون إجابة Travel Mode من القيم: ' . implode(',', array_map(fn($c) => $c->value, TravelModeEnum::cases())),
            'arrangement_type.required' => 'نوع ترتيب السفر مطلوب.',
            'arrangement_type.integer' => 'نوع ترتيب السفر يجب أن يكون رقماً.',
            'arrangement_type.in' => 'نوع ترتيب السفر يجب أن يكون موجودا.',
            'description.string' => 'الوصف يجب أن يكون نصاً.',
            'associated_goals.string' => 'الأهداف المرتبطة يجب أن تكون نصاً.',
            'remarks.string' => 'الملاحظات يجب أن تكون نصاً.',
            'employee_id.exists' => 'الموظف المحدد غير موجود.',
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'تاريخ البداية',
            'end_date' => 'تاريخ النهاية',
            'visit_purpose' => 'غرض الزيارة',
            'visit_place' => 'مكان الزيارة',
            'travel_mode' => 'وضع السفر',
            'arrangement_type' => 'نوع ترتيب السفر',
            'description' => 'الوصف',
            'associated_goals' => 'الأهداف المرتبطة',
        ];
    }

    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        Log::warning('فشل إنشاء طلب رحلة', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل إنشاء طلب رحلة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
