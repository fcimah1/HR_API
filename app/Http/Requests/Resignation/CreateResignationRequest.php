<?php

namespace App\Http\Requests\Resignation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateResignationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'employee_id' => [
                'nullable',
                'integer',
                new \App\Rules\CanRequestForEmployee(),
            ],
            'notice_date' => 'required|date|before_or_equal:resignation_date|after_or_equal:today',
            'resignation_date' => 'required|date|after_or_equal:notice_date|after_or_equal:today',
            'reason' => 'required|string',
            'document_file' => 'nullable|file|mimes:pdf|max:5120', // 5MB max
            'notify_send_to' => ['nullable', 'integer', new \App\Rules\CanNotifyUser()],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'notice_date.required' => 'تاريخ الإخطار مطلوب',
            'notice_date.date' => 'تنسيق تاريخ الإخطار غير صحيح',
            'notice_date.before_or_equal' => 'تاريخ الإخطار يجب أن يكون قبل أو يساوي تاريخ الاستقالة',
            'notice_date.after_or_equal' => 'تاريخ الإخطار يجب أن يكون بعد أو يساوي تاريخ اليوم',
            'resignation_date.required' => 'تاريخ الاستقالة مطلوب',
            'resignation_date.date' => 'تنسيق تاريخ الاستقالة غير صحيح',
            'resignation_date.after_or_equal' => 'تاريخ الاستقالة يجب أن يكون بعد أو يساوي تاريخ الإخطار',
            'resignation_date.after_or_equal' => 'تاريخ الاستقالة يجب أن يكون بعد أو يساوي تاريخ اليوم',
            'reason.required' => 'سبب الاستقالة مطلوب',
            'reason.string' => 'سبب الاستقالة يجب أن يكون نصاً',
            'employee_id.can_request_for_employee' => 'لا يمكنك تقديم طلب استقالة نيابة عن هذا الموظف',
            'document_file.required' => 'الملف مطلوب',
            'document_file.file' => 'الملف يجب أن يكون ملفاً',
            'document_file.mimes' => 'الملف يجب أن يكون من نوع pdf',
            'document_file.max' => 'الملف يجب أن يكون أقل من 5MB', // does not work with max??
            'notify_send_to.integer' => 'معرف الموظف المستلم للإشعار يجب أن يكون رقم',
        ];
    }


    public function attributes(): array
    {
        return [
            'employee_id' => 'معرف الموظف',
            'notice_date' => 'تاريخ الإخطار',
            'resignation_date' => 'تاريخ الاستقالة',
            'reason' => 'سبب الاستقالة',
            'document_file' => 'ملف الاستقالة',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422);

        throw new HttpResponseException($response);
    }
}
