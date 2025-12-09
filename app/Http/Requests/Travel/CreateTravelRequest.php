<?php

namespace App\Http\Requests\Travel;

use App\Enums\TravelModeEnum;
use App\Models\Travel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Schema(
 *     schema="CreateTravelRequest",
 *     type="object",
 *     title="Create Travel Request",
 *     required={"start_date", "end_date", "visit_purpose", "visit_place", "travel_mode", "arrangement_type", "expected_budget", "actual_budget"},
 *     @OA\Property(property="employee_id", type="integer", description="Employee ID (optional, for admin/company)"),
 *     @OA\Property(property="start_date", type="string", format="date", description="Start date of travel"),
 *     @OA\Property(property="end_date", type="string", format="date", description="End date of travel"),
 *     @OA\Property(property="visit_purpose", type="string", description="Purpose of the visit"),
 *     @OA\Property(property="visit_place", type="string", description="Place of visit"),
 *     @OA\Property(property="travel_mode", type="integer", description="Mode of travel (1-5)"),
 *     @OA\Property(property="arrangement_type", type="integer", description="Type of arrangement"),
 *     @OA\Property(property="expected_budget", type="number", format="float", description="Expected budget"),
 *     @OA\Property(property="actual_budget", type="number", format="float", description="Actual budget"),
 *     @OA\Property(property="description", type="string", description="Description"),
 *     @OA\Property(property="associated_goals", type="array", @OA\Items(type="integer"), description="List of associated goal IDs")
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
            'employee_id' => 'nullable|exists:ci_erp_users,user_id', // Optional if creating for self
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'visit_purpose' => 'required|string|max:255',
            'visit_place' => 'required|string|max:255',
            'travel_type_id' => 'required|integer|exists:ci_erp_constants,constants_id',
            'travel_mode' => 'required|integer|in:'.implode(',', array_column(TravelModeEnum::toArray(), 'value')),
            'arrangement_type' => 'required|integer|in:'.implode(',', Travel::getArrangementTypes()),
            'expected_budget' => 'required|numeric|min:0',
            'actual_budget' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'associated_goals' => 'nullable|array',
            'associated_goals.*' => 'integer',
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
            'travel_type_id.required' => 'نوع السفر مطلوب.',
            'travel_type_id.integer' => 'نوع السفر يجب أن يكون رقماً.',
            'travel_type_id.exists' => 'نوع السفر المحدد غير موجود.',
            'travel_mode.required' => 'وضع السفر مطلوب.',
            'travel_mode.integer' => 'وضع السفر يجب أن يكون رقماً.',
            'travel_mode.in' => 'وضع السفر المحدد غير صالح.',
            'arrangement_type.required' => 'نوع الترتيب مطلوب.',
            'arrangement_type.integer' => 'نوع الترتيب يجب أن يكون رقماً.',
            'arrangement_type.in' => 'نوع الترتيب المحدد غير صالح.',
            'expected_budget.required' => 'الميزانية المتوقعة مطلوبة.',
            'expected_budget.numeric' => 'الميزانية المتوقعة يجب أن تكون رقماً.',
            'expected_budget.min' => 'الميزانية المتوقعة يجب أن تكون 0 أو أكثر.',
            'actual_budget.required' => 'الميزانية الفعلية مطلوبة.',
            'actual_budget.numeric' => 'الميزانية الفعلية يجب أن تكون رقماً.',
            'actual_budget.min' => 'الميزانية الفعلية يجب أن تكون 0 أو أكثر.',
            'description.string' => 'الوصف يجب أن يكون نصاً.',
            'associated_goals.array' => 'الأهداف المرتبطة يجب أن تكون مصفوفة.',
            'associated_goals.*.integer' => 'الأهداف المرتبطة يجب أن تكون أرقاماً.',
            'remarks.string' => 'الملاحظات يجب أن تكون نصاً.',
            'employee_id.exists' => 'الموظف المحدد غير موجود.',
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'visit_purpose' => 'Visit Purpose',
            'visit_place' => 'Visit Place',
            'travel_mode' => 'Travel Mode',
            'arrangement_type' => 'Arrangement Type',
            'expected_budget' => 'Expected Budget',
            'actual_budget' => 'Actual Budget',
            'description' => 'Description',
            'associated_goals' => 'Associated Goals',
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
