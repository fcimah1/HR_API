<?php

namespace App\DTOs\Employee;

class CreateEmployeeDTO extends BaseEmployeeDTO
{
    public function __construct(
        public readonly array $userData,
        public readonly array $detailsData
    ) {}

    public static function fromRequest(array $data): self
    {
        $instance = new static([], []);
        
        $userData = $instance->getBasicUserData($data);
        $userData['password'] = isset($data['password']) ? bcrypt($data['password']) : null;
        $userData['company_id'] = (int) $data['company_id'];
        $userData['company_name'] = $data['company_name'];
        $userData['is_active'] = 1;
        $userData['is_logged_in'] = 0;
        $userData['created_at'] = date('Y-m-d H:i:s');

        // تعيين نوع المستخدم الافتراضي كـ "employee" إذا لم يتم تمريره في الطلب
        if (empty($userData['user_type'])) {
            $userData['user_type'] = 'employee';
        }

        // تعيين صورة افتراضية فارغة إذا لم يتم تمرير profile_photo في الطلب
        if (!array_key_exists('profile_photo', $userData) || $userData['profile_photo'] === null) {
            $userData['profile_photo'] = '';
        }

        // تعيين قيمة افتراضية للجنس إذا لم يتم تمرير gender في الطلب
        if (!array_key_exists('gender', $userData) || $userData['gender'] === null) {
            $userData['gender'] = '';
        }
        
        $detailsData = $instance->getEmployeeDetailsData($data);
        $detailsData['company_id'] = (int) $data['company_id'];
        $detailsData['leave_categories'] = $detailsData['leave_categories'] ?? 'all';
        $detailsData['is_work_from_home'] = isset($detailsData['is_work_from_home']) ? ($detailsData['is_work_from_home'] ? 1 : 0) : 0;
        $detailsData['is_eqama'] = isset($detailsData['is_eqama']) ? ($detailsData['is_eqama'] ? 1 : 0) : 1;

        // تعيين مدير افتراضي 0 إذا لم يتم تمرير reporting_manager في الطلب
        if (!array_key_exists('reporting_manager', $detailsData) || $detailsData['reporting_manager'] === null) {
            $detailsData['reporting_manager'] = 0;
        }

        // تعيين قيمة افتراضية للأجر بالساعة 0 إذا لم يتم تمرير hourly_rate في الطلب
        if (!array_key_exists('hourly_rate', $detailsData) || $detailsData['hourly_rate'] === null) {
            $detailsData['hourly_rate'] = 0;
        }

        // تعيين قيم افتراضية لباقي الحقول الرقمية الشائعة لتفادي أخطاء الأعمدة الإلزامية
        $numericDefaults = [
            'department_id',
            'designation_id',
            'basic_salary',
            'marital_status',
            'experience',
            'bank_name',
            'job_type',
            'branch_id',

            // حقول الضرائب والمساهمات (ml_*)
            'ml_tax_category',
            'ml_empployee_epf_rate',
            'ml_empployer_epf_rate',
            'ml_eis_contribution',
            'ml_socso_category',
            'ml_pcb_socso',
            'ml_hrdf',
            'ml_tax_citizenship',
            'zakat_fund',

            // حقول أخرى رقمية مرتبطة بالراتب/العملة
            'salary_payment_method',
            'currency_id',
            'contract_option_id',
            'biotime_id',
        ];

        foreach ($numericDefaults as $field) {
            if (!array_key_exists($field, $detailsData) || $detailsData[$field] === null) {
                $detailsData[$field] = 0;
            }
        }

        // مزامنة نوع الراتب مع اسم العمود القديم salay_type في قاعدة البيانات
        if (isset($detailsData['salary_type']) && $detailsData['salary_type'] !== null) {
            $detailsData['salay_type'] = $detailsData['salary_type'];
        } elseif (!array_key_exists('salay_type', $detailsData) || $detailsData['salay_type'] === null) {
            $detailsData['salay_type'] = 0;
        }

        $detailsData['created_at'] = date('Y-m-d H:i:s');

        return new self(
            userData: $instance->filterNullValues($userData),
            detailsData: $instance->filterNullValues($detailsData)
        );
    }

    public function getUserData(): array
    {
        return $this->userData;
    }

    public function getUserDetailsData(int $userId): array
    {
        return array_merge($this->detailsData, ['user_id' => $userId]);
    }
}
