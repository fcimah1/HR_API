<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * @OA\Schema(
 *     schema="UpdateBasicInfoRequest",
 *     title="Update Basic Info Request",
 *     required={"first_name", "last_name", "contact_number"},
 *     @OA\Property(property="first_name", type="string", example="Ahmed"),
 *     @OA\Property(property="last_name", type="string", example="Mohamed"),
 *     @OA\Property(property="contact_number", type="string", example="0123456789"),
 *     @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
 *     @OA\Property(property="marital_status", type="integer", example=1),
 *     @OA\Property(property="blood_group", type="string", example="A+"),
 *     @OA\Property(property="religion_id", type="integer", example=1),
 *     @OA\Property(property="gender", type="string", example="Male"),
 *     @OA\Property(property="city", type="string", example="Cairo"),
 *     @OA\Property(property="state", type="string", example="Cairo"),
 *     @OA\Property(property="zipcode", type="string", example="12345"),
 *     @OA\Property(property="citizenship_id", type="integer", example=1),
 *     @OA\Property(property="country", type="integer", example=1),
 *     @OA\Property(property="address_1", type="string", example="123 Street"),
 *     @OA\Property(property="address_2", type="string", example="456 Street"),
 *     @OA\Property(property="id_number", type="string", example="123456789")
 * )
 */
class UpdateBasicInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'contact_number' => 'required|string|max:20',
            'date_of_birth' => 'nullable|date',
            'marital_status' => 'nullable|integer',
            'blood_group' => 'nullable|string',
            'religion_id' => 'nullable|integer',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'citizenship_id' => 'nullable|integer',
            'country' => 'nullable|integer',
            'gender' => 'nullable|string|in:Male,Female,1,2',
            'address_1' => 'nullable|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'employee_id' => 'nullable|string|max:50',
        ];
    }
    public function messages(): array
    {
        return [
            'first_name.required' => 'الاسم الأول مطلوب',
            'last_name.required' => 'الاسم الأخير مطلوب',
            'contact_number.required' => 'رقم الاتصال مطلوب',
            'date_of_birth.date' => 'تاريخ الميلاد يجب أن يكون تاريخًا صحيحًا',
            'marital_status.integer' => 'الحالة الاجتماعية يجب أن تكون رقمًا صحيحًا',
            'blood_group.string' => 'فصيلة الدم يجب أن تكون نصًا',
            'religion_id.integer' => 'الديانة يجب أن تكون رقمًا صحيحًا',
            'gender.string' => 'الجنس يجب أن يكون نصًا',
            'city.string' => 'المدينة يجب أن تكون نصًا',
            'state.string' => 'الولاية يجب أن تكون نصًا',
            'zipcode.string' => 'الرمز البريدي يجب أن يكون نصًا',
            'citizenship_id.integer' => 'الجنسية يجب أن تكون رقمًا صحيحًا',
            'country.integer' => 'الدولة يجب أن تكون رقمًا صحيحًا',
            'address_1.string' => 'العنوان الأول يجب أن يكون نصًا',
            'address_2.string' => 'العنوان الثاني يجب أن يكون نصًا',
            'id_number.string' => 'رقم الهوية يجب أن يكون نصًا',
        ];
    }
    public function attributes(): array
    {
        return [
            'first_name' => 'الاسم الأول',
            'last_name' => 'الاسم الأخير',
            'contact_number' => 'رقم الاتصال',
            'date_of_birth' => 'تاريخ الميلاد',
            'marital_status' => 'الحالة الاجتماعية',
            'blood_group' => 'فصيلة الدم',
            'religion_id' => 'الديانة',
            'gender' => 'الجنس',
            'city' => 'المدينة',
            'state' => 'الولاية',
            'zipcode' => 'الرمز البريدي',
            'citizenship_id' => 'الجنسية',
            'country' => 'الدولة',
            'address_1' => 'العنوان الأول',
            'address_2' => 'العنوان الثاني',
            'id_number' => 'رقم الهوية',
        ];
    }
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('date_of_birth')) {
                $dob = $this->input('date_of_birth');
                $age = Carbon::parse($dob)->age;
                if ($age < 18 || $age > 60) {
                    $validator->errors()->add('date_of_birth', 'يجب أن يتراوح العمر بين 18 و 60 سنة');
                }
            }
        });
    }
    public function passedValidation()
    {
        $this->merge([
            'date_of_birth' => $this->input('date_of_birth') ? Carbon::parse($this->input('date_of_birth'))->format('Y-m-d') : null,
        ]);
    }
    public function prepareForValidation()
    {
        if ($this->has('date_of_birth')) {
            $this->merge([
                'date_of_birth' => Carbon::parse($this->input('date_of_birth'))->format('Y-m-d'),
            ]);
        }
    }
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'ال فشل التحقق من البيانات ',
            'errors' => $validator->errors(),
        ], 422));
    }
}
