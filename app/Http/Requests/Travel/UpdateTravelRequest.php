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
 *     schema="UpdateTravelRequest",
 *     type="object",
 *     title="Update Travel Request",
 *     @OA\Property(property="start_date", type="string", format="date", description="تاريخ البداية"),
 *     @OA\Property(property="end_date", type="string", format="date", description="تاريخ النهاية"),
 *     @OA\Property(property="visit_purpose", type="string", description="غرض الزيارة"),
 *     @OA\Property(property="visit_place", type="string", description="مكان الزيارة"),
 *     @OA\Property(property="travel_mode", type="integer", description="وضع السفر (1-5)"),
 *     @OA\Property(property="arrangement_type", type="integer", description="نوع ترتيب السفر"),
 *     @OA\Property(property="description", type="string", description="الوصف"),
 *     @OA\Property(property="associated_goals", type="string", description="الأهداف المرتبطة")
 * )
 */
class UpdateTravelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function all($keys = null)
    {
        $data = parent::all($keys);
        return $data;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'sometimes|required|date|before_or_equal:end_date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'visit_purpose' => 'sometimes|required|string|max:255',
            'visit_place' => 'sometimes|required|string|max:255',
            'travel_mode' => ['sometimes', 'required', 'integer', Rule::in(TravelModeEnum::cases())],
            'arrangement_type' => ['sometimes', 'required', 'integer', Rule::in(Travel::getArrangementTypes(app(SimplePermissionService::class)->getEffectiveCompanyId(Auth::user())))],
            'description' => 'nullable|string',
            'associated_goals' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.date' => 'تاريخ البداية يجب أن يكون تاريخًا صالحًا.',
            'end_date.date' => 'تاريخ النهاية يجب أن يكون تاريخًا صالحًا.',
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون على أو بعد تاريخ البداية.',
            'start_date.before_or_equal' => 'تاريخ البداية يجب أن يكون قبل أو يساوي تاريخ النهاية.',
            'visit_purpose.string' => 'غرض الزيارة يجب أن يكون سلسلة نصية.',
            'visit_purpose.max' => 'غرض الزيارة يجب أن يكون أقل من أو يساوي 255 حرفًا.',
            'visit_place.string' => 'مكان الزيارة يجب أن يكون سلسلة نصية.',
            'visit_place.max' => 'مكان الزيارة يجب أن يكون أقل من أو يساوي 255 حرفًا.',
            'travel_mode.integer' => 'وضع السفر يجب أن يكون عددًا صحيحًا.',
            'travel_mode.in' => 'يجب أن يكون إجابة Travel Mode من القيم: ' . implode(',', array_map(fn($c) => $c->value, TravelModeEnum::cases())),
            'arrangement_type.integer' => 'نوع ترتيب السفر يجب أن يكون عددًا صحيحًا.',
            'arrangement_type.in' => 'نوع ترتيب السفر يجب أن يكون موجودا.',
            'description.string' => 'الوصف يجب أن يكون سلسلة نصية.',
            'associated_goals.string' => 'الأهداف المرتبطة يجب أن تكون نصاً.',
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
        Log::warning('فشل تحديث طلب رحلة', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تحديث طلب رحلة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
