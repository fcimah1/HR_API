<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LeaveApplication;
use App\Models\PayslipStatutoryDeduction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Payroll Deduction Service
 * 
 * Handles salary deductions for tiered sick leave
 * Integrates with existing payroll system (ci_payslip_statutory_deductions)
 */
class PayrollDeductionService
{
    private TieredLeaveService $tieredLeaveService;

    public function __construct(TieredLeaveService $tieredLeaveService)
    {
        $this->tieredLeaveService = $tieredLeaveService;
    }

    /**
     * Create sick leave deduction records when leave is approved
     * 
     * @param int $leaveApplicationId
     * @return bool Success status
     * @throws Exception
     */
    public function createSickLeaveDeductions(int $leaveApplicationId): bool
    {
        $leave = LeaveApplication::with(['employee.user_details', 'leaveType'])
            ->find($leaveApplicationId);

        if (!$leave) {
            throw new Exception("Leave application not found: {$leaveApplicationId}");
        }

        // Only process if payment percentage < 100 (has deduction)
        if ($leave->payment_percentage >= 100) {
            return true; // No deduction needed
        }

        // Get employee's basic salary
        $basicSalary = $leave->employee->user_details->basic_salary ?? 0;
        if ($basicSalary <= 0) {
            throw new Exception("Invalid basic salary for employee {$leave->employee_id}");
        }

        // Calculate daily rate
        $dailyRate = $basicSalary / 30;

        // Get tier splits if available
        $tierInfo = $this->getTierInfoFromLeave($leave);

        if (!empty($tierInfo['splits']) && $tierInfo['is_split']) {
            // Multiple tiers - create separate deduction for each
            foreach ($tierInfo['splits'] as $split) {
                if ($split['deduction_percentage'] > 0) {
                    $this->createDeductionRecord(
                        $leave,
                        $split['days_in_tier'],
                        $dailyRate,
                        $split['deduction_percentage'],
                        $split['tier_order'],
                        $split['tier_description_ar'] ?? 'خصم إجازة مرضية'
                    );
                }
            }
        } else {
            // Single tier or no split info
            $deductionPercentage = 100 - $leave->payment_percentage;
            if ($deductionPercentage > 0) {
                $this->createDeductionRecord(
                    $leave,
                    $leave->leave_days,
                    $dailyRate,
                    $deductionPercentage,
                    $leave->tier_order ?? 1,
                    "خصم إجازة مرضية ({$deductionPercentage}%)"
                );
            }
        }

        // Mark leave as deduction applied
        $leave->salary_deduction_applied = 1;
        $leave->save();

        return true;
    }

    /**
     * Create a single deduction record
     */
    private function createDeductionRecord(
        LeaveApplication $leave,
        float $days,
        float $dailyRate,
        int $deductionPercentage,
        int $tierOrder,
        string $description
    ): void {
        $deductionAmount = $days * $dailyRate * ($deductionPercentage / 100);
        $salaryMonth = Carbon::parse($leave->from_date)->format('Y-m');

        // Insert into ci_payslip_statutory_deductions
        DB::table('ci_payslip_statutory_deductions')->insert([
            'company_id' => $leave->company_id,
            'employee_id' => $leave->employee_id,
            'pay_title' => $description,
            'pay_amount' => round($deductionAmount, 2),
            'pay_month' => $salaryMonth,
            'pay_year' => (int) Carbon::parse($leave->from_date)->format('Y'),
            'is_recurring' => 0, // One-time deduction
            'leave_application_id' => $leave->leave_id,
            'tier_order' => $tierOrder,
            'created_at' => now(),
        ]);
    }

    /**
     * Get tier information from leave application
     */
    private function getTierInfoFromLeave(LeaveApplication $leave): array
    {
        // If leave doesn't have tier splits stored, calculate them
        if (!$leave->country_code) {
            return ['is_split' => false, 'splits' => []];
        }

        $cumulativeDays = $this->tieredLeaveService->getCumulativeSickDaysUsed(
            $leave->employee_id,
            (int) Carbon::parse($leave->from_date)->format('Y')
        );

        return $this->tieredLeaveService->getTieredPaymentInfo(
            $leave->country_code,
            'sick',
            $cumulativeDays - $leave->leave_days, // Before this leave
            $leave->leave_days
        );
    }

    /**
     * Get all deductions for an employee in a specific month
     * 
     * @param int $employeeId
     * @param string $salaryMonth Format: YYYY-MM
     * @return array
     */
    public function getDeductionsForPayroll(int $employeeId, string $salaryMonth): array
    {
        return DB::table('ci_payslip_statutory_deductions')
            ->where('employee_id', $employeeId)
            ->where('pay_month', $salaryMonth)
            ->whereNotNull('leave_application_id')
            ->get()
            ->toArray();
    }

    /**
     * Calculate total deductions for an employee in a month
     * 
     * @param int $employeeId
     * @param string $salaryMonth Format: YYYY-MM
     * @return float Total deduction amount
     */
    public function calculateTotalDeductions(int $employeeId, string $salaryMonth): float
    {
        $total = DB::table('ci_payslip_statutory_deductions')
            ->where('employee_id', $employeeId)
            ->where('pay_month', $salaryMonth)
            ->whereNotNull('leave_application_id')
            ->sum('pay_amount');

        return (float) $total;
    }

    /**
     * Mark deductions as processed after payslip generation
     * 
     * @param array $deductionIds Array of deduction IDs
     * @param int $payslipId Payslip ID reference
     * @return bool
     */
    public function markDeductionsProcessed(array $deductionIds, int $payslipId): bool
    {
        if (empty($deductionIds)) {
            return true;
        }

        DB::table('ci_payslip_statutory_deductions')
            ->whereIn('id', $deductionIds)
            ->update([
                'payslip_id' => $payslipId,
                'is_processed' => 1,
                'updated_at' => now(),
            ]);

        return true;
    }
}
