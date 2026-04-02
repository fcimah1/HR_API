<?php

namespace App\Http\Requests\Employee;

use App\Enums\RelativePlace;
use App\Enums\RelativeRelation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AddFamilyDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Get all Arabic labels from the RelativePlace enum
        $validPlaceValues = [];
        foreach (RelativePlace::cases() as $level) {
            $validPlaceValues[] = $level->value;
        }

        // Get all Arabic labels from the RelativeRelation enum
        $validRelationValues = [];
        foreach (RelativeRelation::cases() as $level) {
            $validRelationValues[] = $level->value;
        }
        return [
            'relative_full_name' => 'required|string|max:255',
            'relative_email' => 'required|email|max:255',
            'relative_phone' => 'required|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'relative_place' => ['required', Rule::in($validPlaceValues)],
            'relative_address' => 'required|string|max:500',
            'relative_relation' => ['required', Rule::in($validRelationValues)]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'relative_full_name.string' => 'اسم القريب يجب أن يكون نص',
            'relative_full_name.required' => 'اسم القريب مطلوب',
            'relative_full_name.max' => 'اسم القريب يجب أن يكون أقل من 255 حرف',
            'relative_email.email' => 'بريد القريب الإلكتروني يجب أن يكون صحيح',
            'relative_email.required' => 'بريد القريب الإلكتروني مطلوب',
            'relative_email.max' => 'بريد القريب الإلكتروني يجب أن يكون أقل من 255 حرف',
            'relative_phone.string' => 'هاتف القريب يجب أن يكون نص',
            'relative_phone.required' => 'هاتف القريب مطلوب',
            'relative_phone.max' => 'هاتف القريب يجب أن يكون أقل من 20 حرف',
            'relative_phone.regex' => 'رقم هاتف القريب غير صحيح',
            'relative_place.string' => 'مكان القريب يجب أن يكون نص',
            'relative_place.required' => 'مكان القريب مطلوب',
            'relative_place.in' => 'مكان القريب غير صحيح',
            'relative_address.string' => 'عنوان القريب يجب أن يكون نص',
            'relative_address.max' => 'عنوان القريب يجب أن يكون أقل من 500 حرف',
            'relative_relation.string' => 'صلة القرابة يجب أن تكون نص',
            'relative_relation.in' => 'صلة القرابة غير صحيحة'
        ];
    }

    /**
     * Get the validation attributes.
     */
    public function attributes(): array
    {
        return [
            'relative_full_name' => 'اسم القريب',
            'relative_email' => 'بريد القريب الإلكتروني',
            'relative_phone' => 'هاتف القريب',
            'relative_place' => 'مكان القريب',
            'relative_address' => 'عنوان القريب',
            'relative_relation' => 'صلة القرابة'
        ];
    }


    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        Log::error('validation errors',[
            'success' => false,
            'status_code' => 422,
            'url' => url()->current(),
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ]);
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ], 422));
    }
}
