<?php

namespace App\Services;

use App\DTOs\Loan\LoanEligibilityDTO;
use App\DTOs\Loan\LoanPreviewDTO;
use App\DTOs\Loan\LoanTierDTO;
use App\Models\AdvanceSalary;
use App\Models\LoanPaymentHistory;
use App\Models\LoanPolicyTier;
use App\Models\User;
use App\Models\UserDetails;
use Illuminate\Support\Collection;

class LoanEligibilityService
{
    /**
     * Get all active loan policy tiers
     */
    public function getActiveTiers(?float $salary = null): Collection
    {
        $tiers = LoanPolicyTier::active()->orderBy('tier_id')->get();

        return $tiers->map(fn($tier) => LoanTierDTO::fromModel($tier, $salary));
    }

    /**
     * Check employee eligibility for loan/advance
     */
    public function checkEligibility(User $user, ?int $employeeId = null): LoanEligibilityDTO
    {
        // Determine target employee (self or specified)
        $targetEmployeeId = $employeeId ?? $user->user_id;
        $employee = User::with('details')->find($targetEmployeeId);

        if (!$employee) {
            return LoanEligibilityDTO::notEligible(['الموظف غير موجود']);
        }

        // Get employee details
        $employeeData = $this->getEmployeeData($employee);

        if ($employeeData['basic_salary'] <= 0) {
            return LoanEligibilityDTO::notEligible(
                ['لم يتم تحديد راتب الموظف في النظام'],
                $employeeData
            );
        }

        // Run eligibility checks
        $blockedReasons = [];
        $checks = [];

        // 1. Date window check (Day 7-21)
        $currentDay = (int) now()->format('d');
        $dateWindowOpen = $currentDay >= 7 && $currentDay <= 21;
        $checks['date_window_open'] = $dateWindowOpen;
        $checks['current_day'] = $currentDay;

        if (!$dateWindowOpen) {
            $blockedReasons[] = 'لا يمكن تقديم الطلب إلا بين الأسبوع الثاني والثالث من الشهر (يوم 7 إلى 21)';
        }

        // 2. Active loan check
        $hasActiveLoan = $this->hasActiveLoan($targetEmployeeId, $user->company_id);
        $checks['has_active_loan'] = $hasActiveLoan;

        if ($hasActiveLoan) {
            $blockedReasons[] = 'لديك قرض/سلفة قائم لم يتم سداده بالكامل';
        }

        // 3. Pending request check
        $hasPendingRequest = $this->hasPendingRequest($targetEmployeeId, $user->company_id);
        $checks['has_pending_request'] = $hasPendingRequest;

        if ($hasPendingRequest) {
            $blockedReasons[] = 'لديك طلب قرض/سلفة قيد الانتظار';
        }

        // 4. Late payment history check
        $hasLatePayments = LoanPaymentHistory::hasLatePaymentHistory($targetEmployeeId);
        $checks['has_late_payment_history'] = $hasLatePayments;

        if ($hasLatePayments) {
            $blockedReasons[] = 'لديك سجل تأخر في سداد قروض سابقة';
        }

        // 5. Loan this year check (once per year rule)
        $hasLoanThisYear = $this->hasApprovedLoanThisYear($targetEmployeeId, $user->company_id);
        $checks['has_loan_this_year'] = $hasLoanThisYear;

        if ($hasLoanThisYear) {
            $blockedReasons[] = 'لديك قرض/سلفة تم الموافقة عليه خلال السنة الحالية';
        }

        if (!empty($blockedReasons)) {
            return LoanEligibilityDTO::notEligible($blockedReasons, $employeeData);
        }

        return LoanEligibilityDTO::eligible($employeeData, $checks);
    }

    /**
     * Preview loan calculation
     */
    public function previewLoan(int $tierId, float $salary, int $requestedMonths): ?LoanPreviewDTO
    {
        $tier = LoanPolicyTier::find($tierId);

        if (!$tier || !$tier->is_active) {
            return null;
        }

        return LoanPreviewDTO::calculate($tier, $salary, $requestedMonths);
    }

    /**
     * Get available tiers for employee with eligibility info
     */
    public function getAvailableTiersForEmployee(User $employee): array
    {
        $salary = $this->getEmployeeSalary($employee);
        $tiers = $this->getActiveTiers($salary);

        $available = [];
        $blocked = [];

        foreach ($tiers as $tierDTO) {
            $tier = LoanPolicyTier::find($tierDTO->tierId);
            $minMonths = $tier->calculateMinMonths($salary);

            // If min months exceeds max months, this tier is not available
            if ($minMonths > $tier->max_months) {
                $blocked[] = [
                    'tier_id' => $tierDTO->tierId,
                    'label' => $tierDTO->label,
                    'reason' => 'القسط الشهري يتجاوز 50% من الراتب حتى مع الحد الأقصى للأشهر',
                ];
            } else {
                $available[] = $tierDTO->toArray();
            }
        }

        return [
            'available_tiers' => $available,
            'blocked_tiers' => $blocked,
        ];
    }

    /**
     * Get complete form initialization data for loan request
     * Returns employee info + available tiers with pre-calculated amounts
     */
    public function getFormInitData(User $requestingUser, int $employeeId): array
    {
        // Get target employee
        $employee = User::with('details')->find($employeeId);

        if (!$employee) {
            return ['error' => 'الموظف غير موجود', 'code' => 404];
        }

        // Get full employee data
        $employeeData = $this->getFullEmployeeData($employee);

        if ($employeeData['monthly_salary'] <= 0) {
            return [
                'error' => 'لم يتم تحديد راتب الموظف في النظام',
                'code' => 400
            ];
        }

        // Check eligibility
        $blockedReasons = [];
        $currentDay = (int) now()->format('d');
        $dateWindowOpen = $currentDay >= 7 && $currentDay <= 21;

        if (!$dateWindowOpen) {
            $blockedReasons[] = 'لا يمكن تقديم الطلب إلا بين الأسبوع الثاني والثالث من الشهر (يوم 7 إلى 21)';
        }

        if ($this->hasActiveLoan($employeeId, $requestingUser->company_id)) {
            $blockedReasons[] = 'لديك قرض/سلفة قائم لم يتم سداده بالكامل';
        }

        if ($this->hasPendingRequest($employeeId, $requestingUser->company_id)) {
            $blockedReasons[] = 'لديك طلب قرض/سلفة قيد الانتظار';
        }

        if (LoanPaymentHistory::hasLatePaymentHistory($employeeId)) {
            $blockedReasons[] = 'لديك سجل تأخر في سداد قروض سابقة';
        }

        if ($this->hasApprovedLoanThisYear($employeeId, $requestingUser->company_id)) {
            $blockedReasons[] = 'لديك قرض/سلفة تم الموافقة عليه خلال السنة الحالية';
        }

        $eligible = empty($blockedReasons);

        // Get available tiers with pre-calculated amounts
        $salary = $employeeData['monthly_salary'];
        $tiers = LoanPolicyTier::active()->orderBy('tier_id')->get();

        $availableTiers = [];
        $blockedTiers = [];

        foreach ($tiers as $tier) {
            $loanAmount = $tier->calculateLoanAmount($salary);
            $minMonths = $tier->calculateMinMonths($salary);
            $maxMonths = $tier->max_months;
            $defaultInstallment = round($loanAmount / $maxMonths, 2);

            // Check if tier is available (min months <= max months)
            if ($minMonths > $maxMonths) {
                $blockedTiers[] = [
                    'tier_id' => $tier->tier_id,
                    'label' => $tier->tier_label_ar,
                    'reason' => 'القسط الشهري يتجاوز 50% من الراتب حتى مع الحد الأقصى للأشهر',
                ];
            } else {
                $availableTiers[] = [
                    'tier_id' => $tier->tier_id,
                    'label' => $tier->tier_label_ar,
                    'loan_amount' => $loanAmount,
                    'max_months' => $maxMonths,
                    'min_months' => $minMonths,
                    'default_installment' => $defaultInstallment,
                ];
            }
        }

        return [
            'eligible' => $eligible,
            'employee' => $employeeData,
            'available_tiers' => $availableTiers,
            'blocked_tiers' => $blockedTiers,
            'blocked_reasons' => $blockedReasons,
        ];
    }

    /**
     * Get full employee data for form (includes all form fields)
     */
    private function getFullEmployeeData(User $employee): array
    {
        $details = $employee->details ?? UserDetails::where('user_id', $employee->user_id)->first();

        $department = null;
        $designation = null;
        $branch = null;

        if ($details) {
            $details->load(['department', 'designation', 'branch']);
            $department = $details->department?->department_name;
            $designation = $details->designation?->designation_name;
            $branch = $details->branch?->branch_name;
        }

        // Get company name: For staff users, look up their parent company
        $companyName = $employee->company_name;
        if (empty($companyName) && $employee->company_id) {
            $companyRecord = User::where('user_id', $employee->company_id)
                ->where('user_type', 'company')
                ->first();
            $companyName = $companyRecord?->company_name;
        }

        return [
            'employee_id' => $employee->user_id,
            'full_name' => $employee->full_name ?? ($employee->first_name . ' ' . $employee->last_name),
            'position' => $designation ?? 'غير محدد',
            'company_id' => $employee->company_id,
            'company_name' => $companyName ?: 'غير محدد',
            'department' => $department ?? 'غير محدد',
            'division' => $branch ?? 'غير محدد',
            'monthly_salary' => $details ? (float) $details->basic_salary : 0.0,
        ];
    }

    /**
     * Get employee salary from UserDetails
     */
    public function getEmployeeSalary(User $employee): float
    {
        $details = UserDetails::where('user_id', $employee->user_id)->first();
        return $details ? (float) $details->basic_salary : 0.0;
    }

    /**
     * Get employee data for response
     */
    private function getEmployeeData(User $employee): array
    {
        $details = $employee->details ?? UserDetails::where('user_id', $employee->user_id)->first();

        $department = null;
        $designation = null;

        if ($details) {
            if ($details->relationLoaded('department')) {
                $department = $details->department?->department_name;
            } else {
                $details->load('department');
                $department = $details->department?->department_name;
            }

            if ($details->relationLoaded('designation')) {
                $designation = $details->designation?->designation_name;
            } else {
                $details->load('designation');
                $designation = $details->designation?->designation_name;
            }
        }

        return [
            'employee_id' => $employee->user_id,
            'full_name' => $employee->full_name ?? ($employee->first_name . ' ' . $employee->last_name),
            'department' => $department ?? 'غير محدد',
            'designation' => $designation ?? 'غير محدد',
            'basic_salary' => $details ? (float) $details->basic_salary : 0.0,
        ];
    }

    /**
     * Check if employee has active (approved, not fully paid) loan
     */
    private function hasActiveLoan(int $employeeId, int $companyId): bool
    {
        return AdvanceSalary::where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->where('status', 1) // Approved
            ->whereColumn('total_paid', '<', 'advance_amount')
            ->exists();
    }

    /**
     * Check if employee has pending request
     */
    private function hasPendingRequest(int $employeeId, int $companyId): bool
    {
        return AdvanceSalary::where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->where('status', 0) // Pending
            ->exists();
    }

    /**
     * Check if employee has approved loan this year
     */
    private function hasApprovedLoanThisYear(int $employeeId, int $companyId): bool
    {
        $currentYear = now()->year;

        return AdvanceSalary::where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->where('status', 1) // Approved
            ->where('created_at', 'like', "{$currentYear}%")
            ->exists();
    }

    /**
     * Validate loan request before creation
     */
    public function validateLoanRequest(
        int $tierId,
        int $requestedMonths,
        float $salary,
        int $employeeId,
        int $companyId
    ): array {
        $errors = [];

        // Get tier
        $tier = LoanPolicyTier::find($tierId);
        if (!$tier || !$tier->is_active) {
            $errors[] = 'نوع القرض/السلفة غير صالح';
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate months
        if ($requestedMonths < 1) {
            $errors[] = 'عدد الأشهر يجب أن يكون 1 على الأقل';
        }

        if ($requestedMonths > $tier->max_months) {
            $errors[] = "عدد الأشهر يتجاوز الحد الأقصى المسموح ({$tier->max_months} شهور)";
        }

        // Validate 50% cap
        if (!$tier->isValidMonths($salary, $requestedMonths)) {
            $loanAmount = $tier->calculateLoanAmount($salary);
            $monthlyInstallment = round($loanAmount / $requestedMonths, 2);
            $maxDeduction = round($salary * 0.50, 2);
            $errors[] = "القسط الشهري ({$monthlyInstallment}) يتجاوز 50% من الراتب ({$maxDeduction})";
        }

        // Check active loan
        if ($this->hasActiveLoan($employeeId, $companyId)) {
            $errors[] = 'لديك قرض/سلفة قائم لم يتم سداده بالكامل';
        }

        // Check pending request
        if ($this->hasPendingRequest($employeeId, $companyId)) {
            $errors[] = 'لديك طلب قرض/سلفة قيد الانتظار';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
