<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Enums\ProductRatingEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="UpdateProductRatingRequest",
 *     title="Update Product Rating Request",
 *     description="طلب تحديث تقييم المنتج",
 *     required={"product_rating"},
 *     @OA\Property(property="product_rating", type="integer", enum={1, 2, 3, 4, 5}, example=4, description="تقييم المنتج من 1 إلى 5")
 * )
 */
class UpdateProductRatingRequest extends FormRequest
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
        return [
            'product_rating' => ['required', 'integer', Rule::in(ProductRatingEnum::values())],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'product_rating.required' => 'التقييم مطلوب',
            'product_rating.integer' => 'التقييم يجب أن يكون رقماً صحيحاً',
            'product_rating.in' => 'التقييم يجب أن يكون بين ' . implode(' , ', ProductRatingEnum::values()),
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من التقييم',
            'errors' => $validator->errors()
        ], 422));
    }
}
