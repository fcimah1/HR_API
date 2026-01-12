<?php

declare(strict_types=1);

namespace App\Http\Requests\SupportTicket;

use App\Enums\TicketCategoryEnum;
use App\Enums\TicketPriorityEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * @OA\Schema(
 *     schema="UpdateTicketRequest",
 *     @OA\Property(
 *         property="subject",
 *         type="string",
 *         maxLength=255,
 *         example="تحديث العنوان",
 *         description="عنوان التذكرة"
 *     ),
 *     @OA\Property(
 *         property="category",
 *         type="string",
 *         example="billing",
 *         description="نوع التذكرة"
 *     ),
 *     @OA\Property(
 *         property="priority",
 *         type="string",
 *         example="critical",
 *         description="أولوية التذكرة"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         maxLength=5000,
 *         description="وصف المشكلة"
 *     ),
 *     @OA\Property(
 *         property="ticket_remarks",
 *         type="string",
 *         maxLength=1000,
 *         nullable=true,
 *         description="ملاحظات إضافية"
 *     )
 * )
 */
class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation - تحويل الأسماء إلى أرقام
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        // تحويل category من اسم إلى رقم
        if ($this->has('category')) {
            $category = TicketCategoryEnum::fromName($this->input('category'));
            if ($category !== null) {
                $data['category_id'] = $category->value;
            }
        } elseif ($this->has('category_id')) {
            $categoryInput = $this->input('category_id');
            if (!is_numeric($categoryInput)) {
                $category = TicketCategoryEnum::fromName($categoryInput);
                $data['category_id'] = $category?->value;
            }
        }

        // تحويل priority من اسم إلى رقم
        if ($this->has('priority')) {
            $priority = TicketPriorityEnum::fromName($this->input('priority'));
            if ($priority !== null) {
                $data['ticket_priority'] = $priority->value;
            }
        } elseif ($this->has('ticket_priority')) {
            $priorityInput = $this->input('ticket_priority');
            if (!is_numeric($priorityInput)) {
                $priority = TicketPriorityEnum::fromName($priorityInput);
                $data['ticket_priority'] = $priority?->value;
            }
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'in:' . implode(',', array_column(TicketCategoryEnum::cases(), 'value'))],
            'ticket_priority' => ['nullable', 'integer', 'in:' . implode(',', array_column(TicketPriorityEnum::cases(), 'value'))],
            'description' => ['nullable', 'string', 'max:5000'],
            'ticket_remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        $acceptedCategories = implode(', ', TicketCategoryEnum::getAcceptedNames());
        $acceptedPriorities = implode(', ', TicketPriorityEnum::getAcceptedNames());

        return [
            'subject.max' => 'عنوان التذكرة يجب ألا يتجاوز 255 حرف',
            'category_id.in' => 'نوع التذكرة غير صالح. القيم المقبولة: ' . $acceptedCategories,
            'ticket_priority.in' => 'أولوية التذكرة غير صالحة. القيم المقبولة: ' . $acceptedPriorities,
            'description.max' => 'الوصف يجب ألا يتجاوز 5000 حرف',
            'ticket_remarks.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'خطأ في البيانات المدخلة',
            'message_en' => 'Validation error',
            'errors' => $validator->errors(),
        ], 422));
    }
}
