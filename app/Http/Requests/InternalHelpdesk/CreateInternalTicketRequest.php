<?php

declare(strict_types=1);

namespace App\Http\Requests\InternalHelpdesk;

use App\Enums\TicketPriorityEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * @OA\Schema(
 *     schema="CreateInternalTicketRequest",
 *     required={"subject", "description"},
 *     @OA\Property(property="subject", type="string", example="مشكلة في النظام"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="priority", type="string", example="high"),
 *     @OA\Property(property="department_id", type="integer", description="مطلوب لصاحب الشركة"),
 *     @OA\Property(property="employee_id", type="integer", description="مطلوب لصاحب الشركة")
 * )
 */
class CreateInternalTicketRequest extends FormRequest
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
            $data['ticket_priority'] = $priority?->value ?? 3;
        } elseif ($this->has('priority')) {
            $data['ticket_priority'] = (int)$this->input('priority');
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $user = $this->user();
        $isCompanyOwner = strtolower(trim($user->user_type ?? '')) === 'company';

        $rules = [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'ticket_priority' => ['nullable', 'integer', 'in:' . implode(',', array_column(TicketPriorityEnum::cases(), 'value'))],
        ];

        // صاحب الشركة يجب أن يختار القسم والموظف
        if ($isCompanyOwner) {
            $rules['department_id'] = ['required', 'integer', 'exists:ci_departments,department_id'];
            $rules['employee_id'] = ['required', 'integer', 'exists:ci_erp_users,user_id'];
        } else {
            // الموظف يمكنه تحديد موظف آخر (subordinate) - اختياري
            $rules['department_id'] = ['nullable', 'integer', 'exists:ci_departments,department_id'];
            $rules['employee_id'] = ['nullable', 'integer', 'exists:ci_erp_users,user_id'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'subject.required' => 'عنوان التذكرة مطلوب',
            'subject.max' => 'عنوان التذكرة يجب ألا يتجاوز 255 حرف',
            'description.required' => 'وصف التذكرة مطلوب',
            'department_id.required' => 'القسم مطلوب',
            'department_id.exists' => 'القسم غير موجود',
            'employee_id.required' => 'الموظف مطلوب',
            'employee_id.exists' => 'الموظف غير موجود',
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
