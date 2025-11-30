<?php

namespace App\Http\Requests\Notification;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class UpdateNotificationSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'module_option' => 'required|string',
            'notify_upon_submission' => 'nullable|array',
            'notify_upon_submission.*' => 'integer|exists:ci_users,user_id',
            'notify_upon_approval' => 'nullable|array',
            'notify_upon_approval.*' => 'integer|exists:ci_users,user_id',
            'approval_method' => 'nullable|string',
            'approval_level' => 'nullable|integer|min:0|max:5',
            'approval_level01' => 'nullable|integer|exists:ci_users,user_id',
            'approval_level02' => 'nullable|integer|exists:ci_users,user_id',
            'approval_level03' => 'nullable|integer|exists:ci_users,user_id',
            'approval_level04' => 'nullable|integer|exists:ci_users,user_id',
            'approval_level05' => 'nullable|integer|exists:ci_users,user_id',
            'skip_specific_approval' => 'nullable|integer|min:0|max:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'module_option.required' => 'يرجى تحديد نوع الوحدة',
            'approval_level.min' => 'يجب أن يكون عدد مستويات الموافقة على الأقل 0',
            'approval_level.max' => 'الحد الأقصى لمستويات الموافقة هو 5',
        ];
    }

    public function attributes(): array
    {
        return [
            'module_option' => 'وحدة',
            'module_key_id' => 'رقم الطلب',
            'status' => 'إجراء',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        Log::warning('فشل تعديل نوع إشعار', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);
      throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تعديل نوع إشعار',
            'errors' => $validator->errors(),
        ], 422));
    }
}
