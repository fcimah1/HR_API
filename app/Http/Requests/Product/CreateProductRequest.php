<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Enums\BarcodeTypeEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="CreateProductRequest",
 *     required={"product_name", "product_qty", "warehouse_id", "category_id", "purchase_price", "retail_price", "reorder_stock", "expiration_date", "product_sku"},
 *     @OA\Property(property="product_name", type="string", maxLength=255),
 *     @OA\Property(property="product_qty", example=10, description="كمية المنتج", type="integer"),
 *     @OA\Property(property="reorder_stock", example=5, description="كمية إعادة التخزين", type="integer"),
 *     @OA\Property(property="warehouse_id", example=1, description="المخزن", type="integer"),
 *     @OA\Property(property="category_id", example=1, description="الفئة", type="integer"),
 *     @OA\Property(property="purchase_price", example=10.5, description="سعر الشراء", type="number", format="float"),
 *     @OA\Property(property="retail_price", example=15.5, description="سعر البيع", type="number", format="float"),
 *     @OA\Property(property="expiration_date", example="2025-12-31", description="تاريخ الانتهاء", type="string", format="date"),
 *     @OA\Property(property="barcode", example="123456789", description="باركود", type="string"),
 *     @OA\Property(property="barcode_type", example="CODE128", description="نوع الباركود", type="string", enum={"CODE39", "CODE93", "CODE128", "ISBN", "CODABAR", "POSTNET", "EAN-8", "EAN-13", "UPC-A", "UPC-E"}),
 *     @OA\Property(property="product_sku", example="SKU123456", description="SKU", type="string"),
 *     @OA\Property(property="product_serial_number", example="SERIAL123456", description="رقم التسلسلي", type="string"),
 *     @OA\Property(property="product_description", example="Product Description", description="وصف المنتج", type="string"),
 *     @OA\Property(property="product_image", type="string", format="binary", description="صورة المنتج")
 * )
 */
class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());
        return [
            'product_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ci_stock_products', 'product_name')
                    ->where('company_id', $effectiveCompanyId)
            ],
            'product_qty' => ['required', 'integer', 'min:0'],
            'reorder_stock' => ['nullable', 'integer', 'min:0'],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('ci_stock_warehouses', 'warehouse_id')
                    ->where('company_id', $effectiveCompanyId)
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('ci_erp_constants', 'constants_id')
                    ->where('type', 'product_category')
                    ->where('company_id', $effectiveCompanyId)
            ],
            'purchase_price' => 'required|numeric|min:0',
            'retail_price' => 'required|numeric|min:0',
            'expiration_date' => 'required|date_format:Y-m-d',
            'barcode' => 'nullable|string|max:255',
            'barcode_type' => ['nullable', 'string', Rule::in(BarcodeTypeEnum::values())],
            'product_sku' => 'required|string|max:255',
            'product_serial_number' => 'nullable|string|max:255',
            'product_description' => 'nullable|string',
            'product_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'product_name.required' => 'اسم المنتج مطلوب',
            'product_name.unique' => 'اسم المنتج موجود بالفعل',
            'product_qty.required' => 'الكمية مطلوبة',
            'warehouse_id.required' => 'المخزن مطلوب',
            'warehouse_id.integer' => 'المخزن يجب أن يكون رقم',
            'warehouse_id.exists' => 'المخزن المختار غير موجود',
            'category_id.required' => 'الفئة مطلوبة',
            'category_id.integer' => 'الفئة يجب أن تكون رقم',
            'category_id.exists' => 'الفئة المختارة غير موجودة',
            'purchase_price.required' => 'سعر الشراء مطلوب',
            'purchase_price.numeric' => 'سعر الشراء يجب أن يكون رقم',
            'retail_price.required' => 'سعر البيع مطلوب',
            'retail_price.numeric' => 'سعر البيع يجب أن يكون رقم',
            'expiration_date.required' => 'تاريخ الانتهاء مطلوب',
            'product_sku.required' => 'رقم المنتج مطلوب',
            'expiration_date.date_format' => 'تاريخ الانتهاء يجب أن يكون في الشكل Y-m-d',
            'barcode_type.in' => 'نوع الباركود غير صحيح',
            'product_image.required' => 'صورة المنتج مطلوبة',
            'product_image.image' => 'صورة المنتج يجب أن تكون صورة',
            'product_image.mimes' => 'صورة المنتج يجب أن تكون من نوع jpeg,png,jpg,gif',
            'product_image.max' => 'صورة المنتج يجب أن لا تتجاوز 2048 كيلوبايت',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل في التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422));
    }
}
