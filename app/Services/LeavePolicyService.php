<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LeaveCountryPolicy;
use App\Repository\LeavePolicyRepository;
use App\Repository\LeavePolicyMappingRepository;
use App\Repository\EmployeeOnetimeLeaveRepository;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Leave Policy Service
 * 
 * Handles country-based leave policy logic
 * Calculates service years, validates requests, manages one-time leaves
 */
class LeavePolicyService
{
    private LeavePolicyRepository $policyRepository;
    private LeavePolicyMappingRepository $mappingRepository;
    private EmployeeOnetimeLeaveRepository $onetimeLeaveRepository;

    public function __construct(
        LeavePolicyRepository $policyRepository,
        LeavePolicyMappingRepository $mappingRepository,
        EmployeeOnetimeLeaveRepository $onetimeLeaveRepository
    ) {
        $this->policyRepository = $policyRepository;
        $this->mappingRepository = $mappingRepository;
        $this->onetimeLeaveRepository = $onetimeLeaveRepository;
    }

    /**
     * Get system leave type name (managed type like 'hajj', 'sick')
     */
    public function getSystemLeaveType(int $companyId, int $leaveTypeId): ?string
    {
        return $this->mappingRepository->getSystemLeaveType($companyId, $leaveTypeId);
    }

    /**
     * Get all policies for a specific country
     * 
     * @param string $countryCode Country code (SA, EG, KW, QA)
     * @param int $companyId Company ID (0 for system defaults)
     * @return array
     */
    public function getPoliciesForCountry(string $countryCode, int $companyId = 0): array
    {
        return $this->policyRepository->getPoliciesForCountry($countryCode, $companyId);
    }

    /**
     * Get company's country code from settings
     * 
     * @param int $companyId
     * @return string Default SA if not set
     */
    public function getCompanyCountryCode(int $companyId): string
    {
        // Query ci_company_settings table
        $setting = DB::table('ci_erp_company_settings')
            ->where('company_id', $companyId)
            ->select('leave_policy_country')
            ->first();

        return $setting->leave_policy_country ?? 'SA';
    }

    /**
     * Calculate employee's service years
     * 
     * @param int $employeeId
     * @return float Service years (e.g., 2.5 for 2 years and 6 months)
     */
    public function calculateServiceYears(int $employeeId): float
    {
        $user = User::with('user_details')->find($employeeId);

        if (!$user || !$user->user_details || !$user->user_details->date_of_joining) {
            return 0.0;
        }

        $joiningDate = Carbon::parse($user->user_details->date_of_joining);
        $now = Carbon::now();

        // Web Logic: Years + (Months / 12) - Ignoring days part
        $diff = $now->diff($joiningDate);
        $years = $diff->y + ($diff->m / 12);

        return round($years, 5);
    }

    /**
     * Get the applicable policy for an employee's leave request
     * 
     * @param int $employeeId
     * @param int $leaveTypeId Leave type constant ID
     * @param int $companyId
     * @param string $countryCode
     * @return LeaveCountryPolicy|null
     */
    public function getApplicablePolicy(
        int $employeeId,
        int $leaveTypeId,
        int $companyId,
        string $countryCode
    ): ?LeaveCountryPolicy {
        // 1. Get system leave type from mapping
        $systemLeaveType = $this->mappingRepository->getSystemLeaveType($companyId, $leaveTypeId);

        if (!$systemLeaveType) {
            return null;
        }

        // 2. Get employee service years
        $serviceYears = $this->calculateServiceYears($employeeId);

        // 3. Find applicable policy (company-specific first, then system default)
        $policy = $this->policyRepository->findMatchingPolicy($countryCode, $systemLeaveType, $serviceYears, $companyId);

        if (!$policy) {
            $policy = $this->policyRepository->findMatchingPolicy($countryCode, $systemLeaveType, $serviceYears, 0);
        }

        return $policy;
    }

    /**
     * Validate leave request against policy rules
     * 
     * @param int $employeeId
     * @param string $systemLeaveType
     * @param int $requestedDays
     * @param LeaveCountryPolicy|null $policy
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateLeaveRequest(
        int $employeeId,
        string $systemLeaveType,
        int $requestedDays,
        ?LeaveCountryPolicy $policy
    ): array {
        $errors = [];

        // 1. Check if policy exists
        if (!$policy) {
            $errors[] = 'لا توجد سياسة إجازة مطابقة لهذا الموظف';
            return ['valid' => false, 'errors' => $errors];
        }

        // 2. Check if policy is active
        if (!$policy->is_active) {
            $errors[] = 'سياسة الإجازة غير نشطة حالياً';
        }

        // 3. Check one-time leave usage
        if ($policy->is_one_time && $this->hasUsedOneTimeLeave($employeeId, $systemLeaveType)) {
            $errors[] = 'تم استخدام هذه الإجازة مسبقاً (تُمنح مرة واحدة فقط)';
        }

        // 4. Check max consecutive days
        if ($policy->max_consecutive_days && $requestedDays > $policy->max_consecutive_days) {
            $errors[] = "الحد الأقصى للأيام المتتالية هو {$policy->max_consecutive_days} يوم";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if employee has used a one-time leave
     * 
     * @param int $employeeId
     * @param string $leaveType System leave type (e.g., 'hajj')
     * @return bool
     */
    public function hasUsedOneTimeLeave(int $employeeId, string $leaveType): bool
    {
        return $this->onetimeLeaveRepository->hasUsed($employeeId, $leaveType);
    }

    /**
     * Validate one-time leave eligibility and usage
     */
    public function validateOneTimeLeaveEligibility(int $employeeId, string $systemLeaveType): array
    {
        $user = User::findOrFail($employeeId);
        $companyId = $user->company_id;
        $countryCode = $this->getCompanyCountryCode($companyId);
        $serviceYears = $this->calculateServiceYears($employeeId);

        // Find matching policy tier
        $policy = $this->policyRepository->findMatchingPolicy($countryCode, $systemLeaveType, $serviceYears, $companyId);
        if (!$policy && $companyId !== 0) {
            $policy = $this->policyRepository->findMatchingPolicy($countryCode, $systemLeaveType, $serviceYears, 0);
        }

        $hasUsed = $this->hasUsedOneTimeLeave($employeeId, $systemLeaveType);

        $canRequest = true;
        $errors = [];

        if ($hasUsed) {
            $canRequest = false;
            $errors[] = 'تم استخدام هذه الإجازة مسبقاً (تُمنح مرة واحدة فقط)';
        }

        if (!$policy) {
            $canRequest = false;
            $errors[] = 'لا توجد سياسة إجازة مطابقة لهذا الموظف (بسبب عدم اكتمال سنوات الخدمة المطلوبة)';
        } elseif (!$policy->is_active) {
            $canRequest = false;
            $errors[] = 'سياسة الإجازة غير نشطة حالياً';
        }

        return [
            'has_used' => $hasUsed,
            'can_request' => $canRequest,
            'errors' => $errors,
            'service_years' => $serviceYears,
            'policy' => $policy ? [
                'policy_id' => $policy->policy_id,
                'min_years' => $policy->service_years_min,
                'max_years' => $policy->service_years_max,
                'entitlement' => $policy->entitlement_days
            ] : null
        ];
    }

    /**
     * Mark one-time leave as used
     * 
     * @param int $employeeId
     * @param string $leaveType
     * @param int $leaveApplicationId
     * @param int $companyId
     * @return void
     * @throws Exception
     */
    public function markOneTimeLeaveUsed(
        int $employeeId,
        string $leaveType,
        int $leaveApplicationId,
        int $companyId
    ): void {
        // Check if already used
        if ($this->hasUsedOneTimeLeave($employeeId, $leaveType)) {
            throw new Exception("الإجازة {$leaveType} تم استخدامها مسبقاً");
        }

        $this->onetimeLeaveRepository->create([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'leave_type' => $leaveType,
            'leave_application_id' => $leaveApplicationId,
            'taken_date' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Get company's active leave policies
     * 
     * @param int $companyId
     * @return array
     */
    public function getCompanyPolicies(int $companyId): array
    {
        // Get country code for company
        $countryCode = $this->getCompanyCountryCode($companyId);

        // Get all policies for this company (custom) or system defaults
        $companyPolicies = $this->policyRepository->getAllPoliciesForCompany($companyId);

        if ($companyPolicies->isEmpty()) {
            // Return system defaults for the country
            $companyPolicies = $this->policyRepository->getSystemPoliciesForCountry($countryCode);
        }

        Log::info('LeavePolicyService::getCompanyPolicies - Success', [
            'company_id' => $companyId,
            'country_code' => $countryCode,
            'policies' => $companyPolicies->toArray(),
        ]);
        return [
            'country_code' => $countryCode,
            'policies' => $companyPolicies->toArray(),
            'is_custom' => $companyPolicies->first()?->company_id == $companyId,
        ];
    }

    /**
     * Save/Initialize company leave policies based on country code
     * 
     * @param int $companyId
     * @param string $countryCode
     * @return array
     * @throws Exception
     */
    public function saveCompanyPolicies(int $companyId, string $countryCode): array
    {
        DB::beginTransaction();

        try {
            // 1. Update company settings table
            DB::table('ci_erp_company_settings')
                ->updateOrInsert(
                    ['company_id' => $companyId],
                    ['leave_policy_country' => $countryCode]
                );

            // 2. Clear old data (handled by repository)
            // Includes policies from ci_leave_policy_countries and constants from ci_erp_constants
            $this->policyRepository->deleteCompanyPolicies($companyId);

            // 3. Delete mappings for this company
            $this->mappingRepository->deleteByCompanyId($companyId);

            // 4. Fetch system default policies for the country (company_id = 0)
            $systemPolicies = $this->policyRepository->getSystemPoliciesForCountry($countryCode);

            if ($systemPolicies->isEmpty()) {
                throw new Exception("لا توجد سياسات افتراضية معرفة لدولة: " . $countryCode);
            }

            // 5. Group policies by leave type to handle unique leave types and mappings
            $policiesByType = $systemPolicies->groupBy('leave_type');

            // Leave type names mapping (same as CI logic)
            // This maps system identifiers to user-friendly names for constants
            $leaveTypeNames = [
                'annual' => ['ar' => 'الإجازة السنوية', 'en' => 'Annual Leave'],
                'sick' => ['ar' => 'الإجازة المرضية', 'en' => 'Sick Leave'],
                'maternity' => ['ar' => 'إجازة الأمومة', 'en' => 'Maternity Leave'],
                'hajj' => ['ar' => 'إجازة الحج', 'en' => 'Hajj Leave'],
                'emergency' => ['ar' => 'إجازة الوفاة والطوارئ', 'en' => 'Emergency Leave'],
            ];

            foreach ($policiesByType as $systemType => $tiers) {
                // Determine names for the constant
                $arName = $leaveTypeNames[$systemType]['ar'] ?? ucfirst($systemType);
                $enName = $leaveTypeNames[$systemType]['en'] ?? ucfirst($systemType);

                // 5b. Generate quota_assign array (0 to 49 years) as per web code
                $quotaAssign = [];
                $tiersInOrder = $tiers->sortBy('tier_order');

                foreach ($tiersInOrder as $tier) {
                    $minYear = (int)($tier->service_years_min ?? 0);
                    $maxYear = $tier->service_years_max !== null ? (int)$tier->service_years_max : 50;
                    $days = (int)$tier->entitlement_days;

                    for ($year = $minYear; $year < $maxYear && $year < 50; $year++) {
                        $quotaAssign[$year] = $days;
                    }
                }

                // Fill gaps with last tier entitlement
                $lastDays = $tiersInOrder->isEmpty() ? 0 : (int)$tiersInOrder->last()->entitlement_days;
                for ($year = 0; $year < 50; $year++) {
                    if (!isset($quotaAssign[$year])) {
                        $quotaAssign[$year] = $lastDays;
                    }
                }

                // Check if constant exists
                $constant = \App\Models\ErpConstant::where('company_id', $companyId)
                    ->where('type', 'leave_type')
                    ->where(function ($q) use ($arName, $enName) {
                        $q->where('category_name', $arName)
                            ->orWhere('category_name', $enName);
                    })
                    ->first();

                $fieldOneData = [
                    'is_quota' => 1,
                    'quota_assign' => $quotaAssign,
                    'quota_unit' => 'days',
                    'is_carry' => 0,
                    'carry_limit' => 0,
                    'is_negative_quota' => 0,
                    'negative_limit' => 0,
                    'enable_leave_accrual' => 0,
                    'policy_based' => 1
                ];

                if (!$constant) {
                    $constant = \App\Models\ErpConstant::create([
                        'company_id' => $companyId,
                        'type' => 'leave_type',
                        'category_name' => $arName,
                        'field_one' => serialize($fieldOneData),
                        'field_two' => '1', // Default to requires approval
                        'field_three' => '1', // Default to active/paid
                        'created_at' => now()->format('Y-m-d H:i:s')
                    ]);
                } else {
                    // Update existing constant with new quotas
                    $constant->update([
                        'field_one' => serialize($fieldOneData)
                    ]);
                }

                $leaveTypeId = $constant->constants_id;

                // 6. Create Mapping between company leave_type_id and system_leave_type
                $this->mappingRepository->create([
                    'company_id' => $companyId,
                    'leave_type_id' => $leaveTypeId,
                    'system_leave_type' => $systemType,
                    'created_at' => now()
                ]);
            }

            DB::commit();

            Log::info("LeavePolicyService::saveCompanyPolicies - Success", [
                'company_id' => $companyId,
                'country_code' => $countryCode,
                'mapping_count' => count($policiesByType)
            ]);

            return [
                'message' => 'تم تهيئة إعدادات الإجازات بنجاح لدولة ' . $countryCode,
                'mapping_count' => count($policiesByType),
                'country_code' => $countryCode,
                'policies' => $this->getCompanyPolicies($companyId)['policies']
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('LeavePolicyService::saveCompanyPolicies - Failed', [
                'company_id' => $companyId,
                'country_code' => $countryCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
