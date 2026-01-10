<?php

declare(strict_types=1);

namespace App\Http\Requests\InternalHelpdesk;

use App\Enums\TicketPriorityEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateInternalTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        // تحويل priority من اسم إلى رقم
        if ($this->has('priority') && !is_numeric($this->input('priority'))) {
            $priority = TicketPriorityEnum::fromName($this->input('priority'));
            if ($priority) {
                $data['ticket_priority'] = $priority->value;
            }
        } elseif ($this->has('priority')) {
            $data['ticket_priority'] = (int)$this->input('priority');
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string', 'max:255'],
            'ticket_priority' => ['sometimes', 'integer', 'in:' . implode(',', array_column(TicketPriorityEnum::cases(), 'value'))],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.max' => 'عنوان التذكرة يجب ألا يتجاوز 255 حرف',
            'ticket_priority.in' => 'الأولوية غير صالحة',
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
