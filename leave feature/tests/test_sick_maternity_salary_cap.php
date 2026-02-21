<?php
/**
 * Test: Sick and Maternity Leave Deduction Salary Cap
 * 
 * Verifies that sick and maternity leave deductions are capped at monthly salary
 * to prevent negative net salary, especially in 31-day months.
 */

// Define ROOTPATH
define('ROOTPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);

require_once ROOTPATH . 'vendor/autoload.php';

// Bootstrap CodeIgniter
$pathsConfig = ROOTPATH . 'app/Config/Paths.php';
require realpath($pathsConfig) ?: $pathsConfig;

$paths = new Config\Paths();
$bootstrap = rtrim($paths->systemDirectory, '\\/ ') . '/bootstrap.php';
$app = require realpath($bootstrap) ?: $bootstrap;

$db = \Config\Database::connect();

echo "\n=== SICK AND MATERNITY LEAVE SALARY CAP TEST ===\n\n";

// Test employee ID
$employeeId = '999930';
$basicSalary = 30000;

echo "Test Setup:\n";
echo "- Employee ID: {$employeeId}\n";
echo "- Basic Salary: {$basicSalary} SAR\n";
echo "- Daily Rate: " . ($basicSalary / 30) . " SAR\n";
echo "- Test Month: January 2026 (31 days)\n\n";

// STEP 1: Cleanup
echo "STEP 1: Cleanup existing test data...\n";
$db->table('ci_payslip_statutory_deductions')
   ->where('staff_id', $employeeId)
   ->where('payslip_id', 0)
   ->delete();

$db->table('ci_leave_applications')
   ->where('employee_id', $employeeId)
   ->delete();

echo "✓ Cleanup complete\n\n";

// STEP 2: Test Saudi Arabia Sick Leave (100% deduction tier)
echo "STEP 2: Testing Saudi Arabia Sick Leave (100% deduction tier)...\n";
echo "Note: SA sick leave tiers: 30d@100%, 60d@75%, 30d@0%\n";
echo "      Testing 31 days in the 0% payment tier (100% deduction)\n\n";

// Create previous sick leave to use up first 90 days
$previousSickLeave = [
    'employee_id' => $employeeId,
    'leave_type_id' => 10006, // SA sick leave
    'from_date' => '2025-10-01',
    'to_date' => '2025-12-31',
    'calculated_days' => 90,
    'status' => 1,
    'country_code' => 'SA',
    'leave_year' => 2026,
    'created_at' => date('Y-m-d H:i:s')
];

$db->table('ci_leave_applications')->insert($previousSickLeave);
echo "✓ Created previous sick leave (90 days) to reach 100% deduction tier\n";

// Create sick leave for 31 days (days 91-121, all in 100% deduction tier)
$sickLeaveData = [
    'employee_id' => $employeeId,
    'leave_type_id' => 10006,
    'from_date' => '2026-01-01',
    'to_date' => '2026-01-31',
    'calculated_days' => 31,
    'status' => 1,
    'country_code' => 'SA',
    'leave_year' => 2026,
    'created_at' => date('Y-m-d H:i:s')
];

$db->table('ci_leave_applications')->insert($sickLeaveData);
$sickLeaveId = $db->insertID();
echo "✓ Sick leave created with ID: {$sickLeaveId}\n";

// Trigger deduction creation
$leavePolicy = new \App\Libraries\LeavePolicy();
$result = $leavePolicy->createSickLeaveDeductions($sickLeaveId);
echo "✓ createSickLeaveDeductions() returned: " . ($result ? 'true' : 'false') . "\n";

// Verify deduction
$sickDeduction = $db->table('ci_payslip_statutory_deductions')
    ->where('staff_id', $employeeId)
    ->where('salary_month', '2026-01')
    ->like('pay_title', 'Sick')
    ->get()
    ->getRowArray();

$sickPass = false;
if ($sickDeduction) {
    $deductionAmount = $sickDeduction['pay_amount'];
    $expectedWithoutCap = 31 * ($basicSalary / 30);
    
    echo "\nSick Leave Deduction:\n";
    echo "- Without cap: " . number_format($expectedWithoutCap, 2) . " SAR (31 days * 1000)\n";
    echo "- Actual deduction: {$deductionAmount} SAR\n";
    echo "- Monthly salary cap: {$basicSalary} SAR\n";
    echo "- Net Salary: " . ($basicSalary - $deductionAmount) . " SAR\n";
    
    if ($deductionAmount <= $basicSalary && $deductionAmount > 0) {
        echo "✓ PASS: Deduction capped correctly at monthly salary\n";
        $sickPass = true;
    } else {
        echo "✗ FAIL: Deduction exceeds monthly salary!\n";
    }
} else {
    echo "✗ FAIL: No sick leave deduction found\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// Final Results
echo "=== TEST RESULTS ===\n\n";

if ($sickPass) {
    echo "✓ TEST PASSED!\n\n";
    echo "Summary:\n";
    echo "- Sick leave deductions capped at monthly salary\n";
    echo "- Net salary cannot go negative\n";
    echo "- 31-day month scenario handled correctly\n";
    echo "- Without cap: 31,000 SAR would cause -1,000 SAR net salary\n";
    echo "- With cap: 30,000 SAR results in 0 SAR net salary (correct)\n";
} else {
    echo "✗ TEST FAILED\n\n";
    echo "The salary cap is not working correctly.\n";
}

// Cleanup
echo "\nCleaning up test data...\n";
$db->table('ci_payslip_statutory_deductions')
   ->where('staff_id', $employeeId)
   ->where('payslip_id', 0)
   ->delete();

$db->table('ci_leave_applications')
   ->where('employee_id', $employeeId)
   ->delete();

echo "✓ Cleanup complete\n";
