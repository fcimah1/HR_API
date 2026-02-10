<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Models\ErpConstant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="UpdateCategoryRequest",
 *     title="UpdateCategoryRequest",
 *     description="طلب تحديث فئة مالية",
 *     @OA\Property(property="name", type="string", example="رواتب - محدث", description="اسم الفئة")
 * )
 */
class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionService = resolve(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        $id = $this->route('id');
        $type = ErpConstant::where('constants_id', $id)->value('type');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ci_erp_constants', 'category_name')->where(function ($query) use ($effectiveCompanyId, $type) {
                    $query->where('company_id', $effectiveCompanyId)
                        ->where('type', $type);
                })->ignore($id, 'constants_id')
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الفئة',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'اسم الفئة موجود بالفعل',
            'name.required' => 'اسم الفئة مطلوب',
            'name.max' => 'اسم الفئة يجب أن لا يتجاوز 255 حرفاً',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
