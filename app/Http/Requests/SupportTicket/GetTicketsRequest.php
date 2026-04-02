<?php

declare(strict_types=1);

namespace App\Http\Requests\SupportTicket;

use App\Enums\TicketCategoryEnum;
use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * @OA\Schema(
 *     schema="GetTicketsRequest",
 *     @OA\Property(property="page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="status", type="string", example="open", description="open, closed"),
 *     @OA\Property(property="category", type="string", example="technical"),
 *     @OA\Property(property="priority", type="string", example="high"),
 *     @OA\Property(property="search", type="string"),
 *     @OA\Property(property="from_date", type="string", format="date"),
 *     @OA\Property(property="to_date", type="string", format="date")
 * )
 */
class GetTicketsRequest extends FormRequest
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

        // تحويل status من اسم إلى رقم
        if ($this->has('status') && !is_numeric($this->input('status'))) {
            $status = TicketStatusEnum::fromName($this->input('status'));
            $data['status'] = $status?->value;
        }

        // تحويل category من اسم إلى رقم
        if ($this->has('category')) {
            $category = TicketCategoryEnum::fromName($this->input('category'));
            $data['category_id'] = $category?->value;
        } elseif ($this->has('category_id') && !is_numeric($this->input('category_id'))) {
            $category = TicketCategoryEnum::fromName($this->input('category_id'));
            $data['category_id'] = $category?->value;
        }

        // تحويل priority من اسم إلى رقم
        if ($this->has('priority') && !is_numeric($this->input('priority'))) {
            $priority = TicketPriorityEnum::fromName($this->input('priority'));
            $data['priority'] = $priority?->value;
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'integer', 'in:' . implode(',', array_column(TicketStatusEnum::cases(), 'value'))],
            'category_id' => ['nullable', 'integer', 'in:' . implode(',', array_column(TicketCategoryEnum::cases(), 'value'))],
            'priority' => ['nullable', 'integer', 'in:' . implode(',', array_column(TicketPriorityEnum::cases(), 'value'))],
            'search' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ];
    }

    public function messages(): array
    {
        $acceptedStatuses = implode(', ', TicketStatusEnum::getAcceptedNames());
        $acceptedCategories = implode(', ', TicketCategoryEnum::getAcceptedNames());
        $acceptedPriorities = implode(', ', TicketPriorityEnum::getAcceptedNames());

        return [
            'page.min' => 'رقم الصفحة يجب أن يكون 1 أو أكثر',
            'per_page.min' => 'عدد العناصر في الصفحة يجب أن يكون 1 أو أكثر',
            'per_page.max' => 'عدد العناصر في الصفحة يجب ألا يتجاوز 100',
            'status.in' => 'الحالة غير صالحة. القيم المقبولة: ' . $acceptedStatuses,
            'category_id.in' => 'نوع التذكرة غير صالح. القيم المقبولة: ' . $acceptedCategories,
            'priority.in' => 'الأولوية غير صالحة. القيم المقبولة: ' . $acceptedPriorities,
            'search.max' => 'نص البحث يجب ألا يتجاوز 255 حرف',
            'from_date.date_format' => 'تنسيق تاريخ البداية غير صالح (Y-m-d)',
            'to_date.date_format' => 'تنسيق تاريخ النهاية غير صالح (Y-m-d)',
            'to_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
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
