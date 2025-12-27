<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Enums\PunchTypeEnum;
use App\Enums\VerifyModeEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * طلب تسجيل مجموعة من سجلات البصمة
 * 
 * @OA\Schema(
 *     schema="BiometricBulkLogsRequest",
 *     type="array",
 *     @OA\Items(
 *         type="object",
 *         required={"company_id", "branch_id", "employee_id", "punch_time", "punch_type", "verify_mode"},
 *         @OA\Property(property="company_id", type="integer", example=24, description="رقم الشركة"),
 *         @OA\Property(property="branch_id", type="integer", example=1, description="رقم الفرع"),
 *         @OA\Property(property="employee_id", type="string", example="073323", description="رقم الموظف في جهاز البصمة"),
 *         @OA\Property(property="punch_time", type="string", format="datetime", example="2025-12-18 08:00:00"),
 *         @OA\Property(property="punch_type", type="integer", example=0, description="نوع البصمة"),
 *         @OA\Property(property="verify_mode", type="integer", example=1, description="طريقة التحقق"),
 *         @OA\Property(property="work_code", type="integer", example=0, description="كود العمل (اختياري)")
 *     )
 * )
 */
class BiometricBulkLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * تحويل البيانات قبل التحقق
     * يتعامل مع الطلب كـ array مباشر وتنضيف البيانات
     */
    protected function prepareForValidation(): void
    {
        // إذا كان الطلب array مباشر، نحوله لـ 'logs'
        if (is_array($this->all()) && !$this->has('logs')) {
            $logs = $this->all();
            
            // تنضيف كل سجل
            $cleanedLogs = array_map(function ($log) {
                return [
                    'company_id' => (int)($log['company_id'] ?? 0),
                    'branch_id' => (int)($log['branch_id'] ?? 0),
                    'employee_id' => trim((string)($log['employee_id'] ?? '')),
                    'punch_time' => $log['punch_time'] ?? '',
                    'punch_type' => (int)($log['punch_type'] ?? 0),
                    'verify_mode' => (int)($log['verify_mode'] ?? 0),
                    'work_code' => (int)($log['work_code'] ?? 0),
                ];
            }, $logs);
            
            $this->merge(['logs' => $cleanedLogs]);
        }
    }

    public function rules(): array
    {
        $punchTypeValues = array_column(PunchTypeEnum::cases(), 'value');
        $verifyModeValues = array_column(VerifyModeEnum::cases(), 'value');

        return [
            // مصفوفة السجلات - كل سجل يحتوي على بياناته كاملة
            'logs' => ['required', 'array', 'min:1'],
            'logs.*.company_id' => ['required', 'integer', 'exists:ci_erp_users,user_id'],
            'logs.*.branch_id' => ['required', 'integer'],
            'logs.*.employee_id' => ['required', 'string', 'max:50'],
            'logs.*.punch_time' => ['required', 'date_format:Y-m-d H:i:s'],
            'logs.*.punch_type' => ['required', 'integer', Rule::in($punchTypeValues)],
            'logs.*.verify_mode' => ['required', 'integer', Rule::in($verifyModeValues)],
            'logs.*.work_code' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'logs.required' => 'يجب إرسال سجلات البصمة',
            'logs.array' => 'السجلات يجب أن تكون مصفوفة',
            'logs.min' => 'يجب إرسال سجل واحد على الأقل',
            'logs.*.company_id.required' => 'رقم الشركة مطلوب في السجل :position',
            'logs.*.company_id.exists' => 'الشركة غير موجودة في النظام في السجل :position',
            'logs.*.branch_id.required' => 'رقم الفرع مطلوب في السجل :position',
            'logs.*.employee_id.required' => 'رقم الموظف مطلوب في السجل :position',
            'logs.*.punch_time.required' => 'وقت البصمة مطلوب في السجل :position',
            'logs.*.punch_time.date_format' => 'صيغة وقت البصمة غير صحيحة في السجل :position (الصيغة المطلوبة: Y-m-d H:i:s)',
            'logs.*.punch_type.required' => 'نوع البصمة مطلوب في السجل :position',
            'logs.*.punch_type.in' => 'نوع البصمة غير صالح في السجل :position',
            'logs.*.verify_mode.required' => 'طريقة التحقق مطلوبة في السجل :position',
            'logs.*.verify_mode.in' => 'طريقة التحقق غير صالحة في السجل :position',
        ];
    }

    /**
     * الحصول على الـ logs (سواء من wrapper أو مباشر)
     */
    public function getLogs(): array
    {
        return $this->logs ?? $this->all();
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'خطأ في البيانات المدخلة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
