<?php

declare(strict_types=1);

namespace App\Http\Requests\Document;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class CreateSignatureDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('staff_ids')) {
            $staffIds = $this->staff_ids;

            // Handle strings (e.g., "702,767") or arrays containing strings (e.g., ["702,767"])
            if (is_string($staffIds)) {
                $staffIds = explode(',', $staffIds);
            }

            if (is_array($staffIds)) {
                $flattened = [];
                foreach ($staffIds as $id) {
                    if (is_string($id) && str_contains($id, ',')) {
                        $flattened = array_merge($flattened, explode(',', $id));
                    } else {
                        $flattened[] = $id;
                    }
                }
                // Filter empty values and ensure elements are integers where possible
                $staffIds = array_map(function ($val) {
                    $trimmed = trim((string) $val);
                    return is_numeric($trimmed) ? (int) $trimmed : $val;
                }, array_filter($flattened, fn($v) => !is_null($v) && trim((string)$v) !== ''));
            }

            $this->merge([
                'staff_ids' => array_values($staffIds),
            ]);
        }

        // Handle signature_task string labels (exact match as requested)
        if ($this->has('signature_task')) {
            $task = $this->signature_task;
            if (is_string($task)) {
                $taskTrimmed = trim($task);
                if ($taskTrimmed === 'ملف onboarding') {
                    $this->merge(['signature_task' => 1]);
                } elseif ($taskTrimmed === 'ملف offboarding') {
                    $this->merge(['signature_task' => 0]);
                }
            }
        }
    }

    public function rules(): array
    {
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $user = Auth::user();
        $companyId = $permissionService->getEffectiveCompanyId($user);

        $subordinateIds = [];
        if (!$permissionService->isCompanyOwner($user)) {
            $subordinates = $permissionService->getEmployeesByHierarchy(
                $user->user_id,
                $companyId,
                true // Include self
            );
            $subordinateIds = array_column($subordinates, 'user_id');
        }

        return [
            'document_name' => 'required|string|max:255',
            'share_with_employees' => 'required|integer', // 0 for all
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => [
                'integer',
                function ($attribute, $value, $fail) use ($companyId, $subordinateIds, $user, $permissionService) {
                    $exists = \Illuminate\Support\Facades\DB::table('ci_erp_users')
                        ->where('user_id', $value)
                        ->where('company_id', $companyId)
                        ->exists();

                    if (!$exists) {
                        $fail('معرف الموظف غير موجود في الشركة.');
                        return;
                    }

                    if (!$permissionService->isCompanyOwner($user) && !in_array($value, $subordinateIds)) {
                        $fail('لا تملك الصلاحية لإضافة هذا الموظف (يجب أن يكون من التابعين لك).');
                    }
                },
            ],
            'signature_task' => ['required', 'integer', 'in:0,1'],
            'document_file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png,gif|max:10240', // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'document_name.required' => 'يرجى إدخال اسم المستند',
            'share_with_employees.required' => 'يرجى تحديد حالة المشاركة مع الموظفين',
            'signature_task.required' => 'يرجى تحديد حالة مهمة التوقيع',
            'signature_task.in' => 'يجب اختيار مهمة التوقيع بين "ملف onboarding" أو "ملف offboarding"',
            'signature_task.integer' => 'يجب اختيار مهمة التوقيع بين "ملف onboarding" أو "ملف offboarding"',
            'document_file.required' => 'يرجى اختيار ملف المستند',
            'document_file.mimes' => 'يجب أن يكون الملف من نوع: pdf, doc, docx, jpg, jpeg, png, gif',
            'document_file.max' => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
