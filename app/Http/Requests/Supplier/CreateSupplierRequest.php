<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * @OA\Schema(
 *     schema="CreateSupplierRequest",
 *     type="object",
 *     title="Create Supplier Request",
 *     required={"supplier_name", "country"},
 *     @OA\Property(property="supplier_name", type="string", description="اسم المورد", example="شركة توريدات الخليج"),
 *     @OA\Property(property="registration_no", type="string", description="رقم السجل التجاري", example="CR12345678"),
 *     @OA\Property(property="email", type="string", format="email", description="البريد الالكتروني", example="info@gulf-supplies.com"),
 *     @OA\Property(property="contact_number", type="string", description="رقم الاتصال", example="0556543210"),
 *     @OA\Property(property="website_url", type="string", description="رابط الموقع", example="https://gulf-supplies.com"),
 *     @OA\Property(property="address_1", type="string", description="العنوان 1", example="المنطقة الصناعية"),
 *     @OA\Property(property="address_2", type="string", description="العنوان 2", example="بلوك 14"),
 *     @OA\Property(property="city", type="string", description="المدينة", example="جدة"),
 *     @OA\Property(property="state", type="string", description="الدولة/المنطقة", example="مكة المكرمة"),
 *     @OA\Property(property="zipcode", type="string", description="الرمز البريدي", example="54321"),
 *     @OA\Property(property="country", type="integer", description="معرف الدولة", example=1)
 * )
 */
class CreateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_name' => 'required|string|max:255',
            'registration_no' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'contact_number' => 'nullable|string|max:255',
            'website_url' => 'nullable|string|max:255',
            'address_1' => 'nullable|string',
            'address_2' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:255',
            'country' => 'required|integer|exists:ci_countries,country_id',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_name.required' => 'اسم المورد مطلوب',
            'supplier_name.string' => 'اسم المورد يجب ان يكون نص',
            'supplier_name.max' => 'اسم المورد يجب ان يكون اقل من 255 حرف',
            'registration_no.string' => 'رقم السجل التجاري يجب ان يكون نص',
            'registration_no.max' => 'رقم السجل التجاري يجب ان يكون اقل من 255 حرف',
            'email.email' => 'البريد الالكتروني يجب ان يكون بريد الكتروني صحيح',
            'email.max' => 'البريد الالكتروني يجب ان يكون اقل من 255 حرف',
            'contact_number.string' => 'رقم الاتصال يجب ان يكون نص',
            'contact_number.max' => 'رقم الاتصال يجب ان يكون اقل من 255 حرف',
            'website_url.string' => 'رابط الموقع يجب ان يكون نص',
            'website_url.max' => 'رابط الموقع يجب ان يكون اقل من 255 حرف',
            'address_1.string' => 'العنوان 1 يجب ان يكون نص',
            'address_2.string' => 'العنوان 2 يجب ان يكون نص',
            'city.string' => 'المدينة يجب ان تكون نص',
            'city.max' => 'المدينة يجب ان تكون اقل من 255 حرف',
            'state.string' => 'الدولة يجب ان تكون نص',
            'state.max' => 'الدولة يجب ان تكون اقل من 255 حرف',
            'zipcode.string' => 'الرمز البريدي يجب ان يكون نص',
            'zipcode.max' => 'الرمز البريدي يجب ان يكون اقل من 255 حرف',
            'country.required' => 'الدولة مطلوبة',
            'country.exists' => 'الدولة غير موجودة',
            'country.integer' => 'الدولة يجب ان تكون رقم',
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_name' => 'اسم المورد',
            'registration_no' => 'رقم السجل التجاري',
            'email' => 'البريد الالكتروني',
            'contact_number' => 'رقم الاتصال',
            'website_url' => 'رابط الموقع',
            'address_1' => 'العنوان 1',
            'address_2' => 'العنوان 2',
            'city' => 'المدينة',
            'state' => 'الدولة',
            'zipcode' => 'الرمز البريدي',
            'country' => 'الدولة',
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
