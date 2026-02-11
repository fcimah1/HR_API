<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * @OA\Schema(
 *     schema="CreateWarehouseRequest",
 *     type="object",
 *     title="Create Warehouse Request",
 *     required={"warehouse_name", "country"},
 *     @OA\Property(property="warehouse_name", type="string", description="اسم المستودع", example="مستودع الرياض الرئيسى"),
 *     @OA\Property(property="contact_number", type="string", description="رقم الاتصال", example="0501234567"),
 *     @OA\Property(property="pickup_location", type="integer", description="معرف موقع الاستلام", example=1),
 *     @OA\Property(property="address_1", type="string", description="العنوان 1", example="شارع الملك فهد"),
 *     @OA\Property(property="address_2", type="string", description="العنوان 2", example="حى العليا"),
 *     @OA\Property(property="city", type="string", description="المدينة", example="الرياض"),
 *     @OA\Property(property="state", type="string", description="المنطقة/الدولة", example="الرياض"),
 *     @OA\Property(property="zipcode", type="string", description="الرمز البريدى", example="12345"),
 *     @OA\Property(property="country", type="integer", description="معرف الدولة", example=1),
 *     @OA\Property(property="status", type="integer", description="الحالة (0: غير نشط, 1: نشط)", example=1)
 * )
 */
class CreateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_name' => 'required|string|max:200',
            'contact_number' => 'nullable|string|max:255',
            'pickup_location' => 'nullable|integer',
            'address_1' => 'nullable|string',
            'address_2' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:255',
            'country' => 'required|integer|exists:ci_countries,country_id',
            'status' => 'nullable|integer|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_name.required' => 'اسم المستودع مطلوب',
            'warehouse_name.string' => 'اسم المستودع يجب ان يكون نص',
            'warehouse_name.max' => 'اسم المستودع يجب ان يكون اقل من 200 حرف',
            'contact_number.string' => 'رقم الاتصال يجب ان يكون نص',
            'contact_number.max' => 'رقم الاتصال يجب ان يكون اقل من 255 حرف',
            'pickup_location.integer' => 'موقع الاستلام يجب ان يكون رقم',
            'address_1.string' => 'العنوان 1 يجب ان يكون نص',
            'address_2.string' => 'العنوان 2 يجب ان يكون نص',
            'city.string' => 'المدينة يجب ان تكون نص',
            'city.max' => 'المدينة يجب ان تكون اقل من 255 حرف',
            'state.string' => 'الدولة يجب ان تكون نص',
            'state.max' => 'الدولة يجب ان تكون اقل من 255 حرف',
            'zipcode.string' => 'الرمز البريدي يجب ان يكون نص',
            'zipcode.max' => 'الرمز البريدي يجب ان يكون اقل من 255 حرف',
            'country.required' => 'الدولة مطلوبة',
            'country.integer' => 'الدولة يجب ان تكون رقم',
            'country.exists' => 'الدولة غير موجودة',
            'status.integer' => 'الحالة يجب ان تكون رقم',
            'status.in' => 'الحالة يجب ان تكون 0 او 1',
        ];
    }

    public function attributes(): array
    {
        return [
            'warehouse_name' => 'اسم المستودع',
            'contact_number' => 'رقم الاتصال',
            'pickup_location' => 'موقع الاستلام',
            'address_1' => 'العنوان 1',
            'address_2' => 'العنوان 2',
            'city' => 'المدينة',
            'state' => 'الدولة',
            'zipcode' => 'الرمز البريدي',
            'country' => 'الدولة',
            'status' => 'الحالة',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'فشل فى التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
