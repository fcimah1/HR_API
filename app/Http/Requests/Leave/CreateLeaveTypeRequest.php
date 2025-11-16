<?php

namespace App\Http\Requests\Leave;

    use Illuminate\Foundation\Http\FormRequest;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Contracts\Validation\Validator;
    use Illuminate\Http\Exceptions\HttpResponseException;
    use Illuminate\Support\Facades\Log;

class CreateLeaveTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        
        // Only HR, Admin, and Company can create leave types
        return in_array($user->user_type, ['company', 'admin', 'hr']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'leave_type_name' => 'required|string|max:255|unique:ci_erp_constants,category_name',
            'leave_type_short_name' => 'nullable|string|max:100',
            'leave_days' => 'required|integer|min:0|max:365',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'leave_type_name.required' => 'اسم نوع الإجازة مطلوب',
            'leave_type_name.unique' => 'اسم نوع الإجازة موجود بالفعل',
            'leave_type_name.string' => 'اسم نوع الإجازة يجب أن يكون نص',
            'leave_type_name.max' => 'اسم نوع الإجازة لا يجب أن يتجاوز 255 حرف',
            'leave_type_short_name.string' => 'الاسم المختصر يجب أن يكون نص',
            'leave_type_short_name.max' => 'الاسم المختصر لا يجب أن يتجاوز 100 حرف',
            'leave_days.required' => 'عدد أيام الإجازة مطلوب',
            'leave_days.integer' => 'عدد أيام الإجازة يجب أن يكون رقماً صحيحاً',
            'leave_days.min' => 'عدد أيام الإجازة يجب أن يكون 0 أو أكثر',
            'leave_days.max' => 'عدد أيام الإجازة لا يجب أن يتجاوز 365 يوماً',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'leave_type_name' => 'اسم نوع الإجازة',
            'leave_type_short_name' => 'الاسم المختصر',
            'leave_days' => 'عدد أيام الإجازة',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization()
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإنشاء أنواع إجازات جديدة'
            ], 403)
        );
    }

    protected function failedValidation(Validator $validator)
    {
        Log::warning('فشل إنشاء نوع إجازة', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل إنشاء نوع إجازة',
            'errors' => $validator->errors(),
        ], 422));
    }
}

