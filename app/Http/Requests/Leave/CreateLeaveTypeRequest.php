<?php

namespace App\Http\Requests\Leave;

use App\Services\SimplePermissionService;
use Illuminate\Foundation\Http\FormRequest;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Contracts\Validation\Validator;
    use Illuminate\Http\Exceptions\HttpResponseException;
    use Illuminate\Support\Facades\Log;

class CreateLeaveTypeRequest extends FormRequest
{
    public $simplePermissionService;
    public function __construct(
        private readonly SimplePermissionService $permissionService
    ) {
        $this->simplePermissionService = $permissionService;
    }
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();

        // return $this->simplePermissionService->checkPermission($user, 'leave_type2');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'leave_type_name' => 'required|string|max:255|unique:ci_erp_constants,category_name',
            'requires_approval' => 'nullable|boolean',

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
            'requires_approval.boolean' => 'حقل يتطلب موافقة يجب أن يكون قيمة منطقية (0 أو 1)',
            'is_paid_leave.boolean' => 'حقل إجازة مدفوعة يجب أن يكون قيمة منطقية (0 أو 1)',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'leave_type_name' => 'اسم نوع الإجازة',
            'requires_approval' => 'يتطلب موافقة',
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

