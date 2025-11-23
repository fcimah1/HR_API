<?php

namespace App\Http\Requests\TravelType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\ErpConstant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Schema(
 *     schema="CreateTravelTypeRequest",
 *     required={"travel_name"},
 *     @OA\Property(property="travel_name", type="string", example="Personal Arrangement")
 * )
 */
class CreateTravelTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [
                'travel_name' => 'required|string|max:255',
            ];
        }

        $companyId = $user->company_id;

        return [
            'travel_name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($companyId) {
                    $exists = ErpConstant::where('company_id', $companyId)
                        ->where('type', 'travel_type')
                        ->where('category_name', $value)
                        ->exists();

                    if ($exists) {
                        $fail('نوع السفر موجود بالفعل');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'travel_name.required' => 'اسم نوع السفر مطلوب',
            'travel_name.string' => 'اسم نوع السفر يجب أن يكون نصاً',
            'travel_name.max' => 'اسم نوع السفر يجب ألا يتجاوز 255 حرفاً',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        Log::warning('CreateTravelTypeRequest validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل في التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
