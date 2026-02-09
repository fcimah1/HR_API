<?php

declare(strict_types=1);

namespace App\Http\Requests\Visitor;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="CreateVisitorRequest",
 *     title="CreateVisitorRequest",
 *     description="طلب إضافة زائر جديد",
 *     required={"visitor_name", "department_id", "visit_date", "check_in"},
 *     @OA\Property(property="visitor_name", type="string", example="أحمد محمد"),
 *     @OA\Property(property="department_id", type="integer", example=1),
 *     @OA\Property(property="visit_date", type="string", format="date", example="2023-10-25"),
 *     @OA\Property(property="check_in", type="string", example="09:00"),
 *     @OA\Property(property="visit_purpose", type="string", example="مقابلة عمل"),
 *     @OA\Property(property="phone", type="string", example="0501234567"),
 *     @OA\Property(property="email", type="string", format="email", example="visitor@example.com"),
 *     @OA\Property(property="check_out", type="string", example="11:00"),
 *     @OA\Property(property="address", type="string", example="الرياض"),
 *     @OA\Property(property="description", type="string", example="ملاحظات إضافية")
 * )
 */
class CreateVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionService = resolve(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'visitor_name' => 'required|string|max:255',
            'department_id' => [
                'required',
                'integer',
                Rule::exists('ci_departments', 'department_id')
                    ->where('company_id', $effectiveCompanyId)
            ],
            'visit_date' => 'required|string|max:255|date_format:Y-m-d',
            'check_in' => 'required|string|max:255|date_format:H:i',
            'visit_purpose' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'check_out' => 'required|string|max:255|date_format:H:i',
            'address' => 'required|string',
            'description' => 'nullable|string',
        ];
    }

    public function attributes(): array
    {
        return [
            'visitor_name' => 'اسم الزائر',
            'department_id' => 'القسم',
            'visit_date' => 'تاريخ الزيارة',
            'check_in' => 'وقت الدخول',
            'visit_purpose' => 'غرض الزيارة',
            'phone' => 'الهاتف',
            'email' => 'البريد الإلكتروني',
            'check_out' => 'وقت الخروج',
            'address' => 'العنوان',
            'description' => 'الوصف',
        ];
    }

    public function messages(): array
    {
        return [
            'visitor_name.required' => 'اسم الزائر مطلوب',
            'visitor_name.string' => 'اسم الزائر يجب أن يكون نصاً',
            'visitor_name.max' => 'اسم الزائر يجب أن لا يتجاوز 255 حرفاً',
            'department_id.required' => 'القسم مطلوب',
            'department_id.integer' => 'القسم يجب أن يكون رقماً',
            'department_id.exists' => 'القسم غير موجود',
            'visit_date.required' => 'تاريخ الزيارة مطلوب',
            'visit_date.string' => 'تاريخ الزيارة يجب أن يكون نصاً',
            'visit_date.max' => 'تاريخ الزيارة يجب أن لا يتجاوز 255 حرفاً',
            'visit_date.date_format' => 'تاريخ الزيارة يجب أن يكون بصيغة Y-m-d',
            'check_in.required' => 'وقت الدخول مطلوب',
            'check_in.string' => 'وقت الدخول يجب أن يكون نصاً',
            'check_in.max' => 'وقت الدخول يجب أن لا يتجاوز 255 حرفاً',
            'check_in.date_format' => 'وقت الدخول يجب أن يكون بصيغة H:i',
            'visit_purpose.required' => 'غرض الزيارة مطلوب',
            'visit_purpose.string' => 'غرض الزيارة يجب أن يكون نصاً',
            'visit_purpose.max' => 'غرض الزيارة يجب أن لا يتجاوز 255 حرفاً',
            'phone.required' => 'الهاتف مطلوب',
            'phone.string' => 'الهاتف يجب أن يكون نصاً',
            'phone.max' => 'الهاتف يجب أن لا يتجاوز 255 حرفاً',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'email.max' => 'البريد الإلكتروني يجب أن لا يتجاوز 255 حرفاً',
            'check_out.required' => 'وقت الخروج مطلوب',
            'check_out.string' => 'وقت الخروج يجب أن يكون نصاً',
            'check_out.max' => 'وقت الخروج يجب أن لا يتجاوز 255 حرفاً',
            'check_out.date_format' => 'وقت الخروج يجب أن يكون بصيغة H:i',
            'address.required' => 'العنوان مطلوب',
            'address.string' => 'العنوان يجب أن يكون نصاً',
            'description.string' => 'الوصف يجب أن يكون نصاً',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
