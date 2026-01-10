<?php

declare(strict_types=1);

namespace App\Http\Requests\InternalHelpdesk;

use App\Enums\TicketPriorityEnum;
use App\Models\Department;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class GetInternalTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        // تحويل status
        if ($this->has('status') && !is_numeric($this->input('status'))) {
            $statusMap = ['open' => 1, 'closed' => 2];
            $data['status'] = $statusMap[strtolower($this->input('status'))] ?? null;
        }

        // تحويل priority من اسم إلى رقم
        if ($this->has('priority') && !is_numeric($this->input('priority'))) {
            $priority = TicketPriorityEnum::fromName($this->input('priority'));
            $data['priority'] = $priority?->value;
        }

        // تحويل department من اسم إلى ID
        if ($this->has('department_id') && !is_numeric($this->input('department_id'))) {
            $user = Auth::user();
            $companyId = $user->company_id == 0 ? $user->user_id : $user->company_id;

            $department = Department::where('company_id', $companyId)
                ->where('department_name', 'like', '%' . $this->input('department_id') . '%')
                ->first();

            // إذا لم يوجد قسم بهذا الاسم، نرجع -1 لإظهار نتائج فارغة
            $data['department_id'] = $department?->department_id ?? -1;
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
            'status' => ['nullable', 'integer', 'in:1,2'],
            'priority' => ['nullable', 'integer', 'in:' . implode(',', array_column(TicketPriorityEnum::cases(), 'value'))],
            'department_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'page.min' => 'رقم الصفحة يجب أن يكون 1 أو أكثر',
            'per_page.max' => 'عدد العناصر يجب ألا يتجاوز 100',
            'status.in' => 'الحالة غير صالحة (1=مفتوحة، 2=مغلقة)',
            'priority.in' => 'الأولوية غير صالحة',
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
