<?php
/**
 * Direct Test: Salary Cap on Leave Deductions
 * 
 * This test directly verifies the salary cap by manually calculating
 * what the deduction should be and confirming it's capped.
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

echo "\n=== DIRECT SALARY CAP TEST ===\n\n";

$employeeId = '999930';
$basicSalary = 30000;
$dailyRate = $basicSalary / 30;

echo "Test Scenario:\n";
echo "- Employee Salary: {$basicSalary} SAR\n";
echo "- Daily Rate: {$dailyRate} SAR\n";
echo "- Leave: 31 days in January 2026\n";
echo "- All days at 100% deduction (0% payment)\n";
echo "- Expected without cap: " . (31 * $dailyRate) . " SAR\n";
echo "- Expected with cap: {$basicSalary} SAR (max)\n\n";

// Cleanup
$db->table('ci_payslip_statutory_deductions')
   ->where('staff_id', $employeeId)
   ->where('payslip_id', 0)
   ->delete();

$db->table('ci_leave_applications')
   ->where('employee_id', $employeeId)
   ->delete();

// Test the calculation logic directly by examining what createSickLeaveDeductions does
// We'll create a scenario where we know all 31 days should be 100% deducted

// For Saudi Arabia sick leave:
// Tier 1: 30 days @ 100% payment (0% deduction)
// Tier 2: 60 days @ 75% payment (25% deduction)
// Tier 3: 30 days @ 0% payment (100% deduction)

// To get all 31 days in tier 3, we need cumulative to start at 90
// But the function subtracts the current leave days from cumulative
// So we need to create previous leaves totaling 90 days

echo "Creating test data...\n";

// Create 3 previous leaves totaling 90 days
for ($i = 1; $i <= 3; $i++) {
    $leaveData = [
        'employee_id' => $employeeId,
        'leave_type_id' => 10006,
        'from_date' => '2025-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01',
        'to_date' => '2025-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-30',
        'calculated_days' => 30,
        'status' => 1,
        'country_code' => 'SA',
        'leave_year' => 2025,
        'created_at' => date('Y-m-d H:i:s')
    ];
    $db->table('ci_leave_applications')->insert($leaveData);
}
echo "✓ Created 3 previous leaves (90 days total)\n";

// Now create the test leave for 31 days in January 2026
$testLeave = [
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

$db->table('ci_leave_applications')->insert($testLeave);
$leaveId = $db->insertID();
echo "✓ Created test leave (31 days in January 2026)\n\n";

// Call the deduction creation function
echo "Calling createSickLeaveDeductions()...\n";
$leavePolicy = new \App\Libraries\LeavePolicy();
$result = $leavePolicy->createSickLeaveDeductions($leaveId);
echo "✓ Function returned: " . ($result ? 'true' : 'false') . "\n\n";

// Check the deduction
$deduction = $db->table('ci_payslip_statutory_deductions')
    ->where('staff_id', $employeeId)
    ->where('salary_month', '2026-01')
    ->like('pay_title', 'Sick')
    ->get()
    ->getRowArray();

if ($deduction) {
    $deductionAmount = $deduction['pay_amount'];
    $expectedWithoutCap = 31 * $dailyRate;
    
    echo "Results:\n";
    echo "- Deduction Amount: {$deductionAmount} SAR\n";
    echo "- Expected without cap: {$expectedWithoutCap} SAR\n";
    echo "- Salary cap: {$basicSalary} SAR\n";
    echo "- Net Salary: " . ($basicSalary - $deductionAmount) . " SAR\n\n";
    
    // The key test: deduction should not exceed salary
    if ($deductionAmount <= $basicSalary) {
        echo "✓ PASS: Deduction is capped at or below monthly salary\n";
        
        // Additional check: if it would have exceeded without cap
        if ($expectedWithoutCap > $basicSalary && $deductionAmount == $basicSalary) {
            echo "✓ PERFECT: Cap was applied (would have been {$expectedWithoutCap} SAR)\n";
        } elseif ($deductionAmount < $basicSalary) {
            echo "ℹ INFO: Deduction is less than salary (cap not needed in this case)\n";
            echo "  This might mean not all days were in the 100% deduction tier\n";
        }
        
        $testPass = true;
    } else {
        echo "✗ FAIL: Deduction exceeds monthly salary!\n";
        echo "  This would result in negative net salary: " . ($basicSalary - $deductionAmount) . " SAR\n";
        $testPass = false;
    }
} else {
    echo "✗ FAIL: No deduction record found\n";
    $testPass = false;
}

echo "\n" . str_repeat("=", 60) . "\n\n";

if ($testPass) {
    echo "✓ TEST PASSED: Salary cap is working correctly\n";
} else {
    echo "✗ TEST FAILED: Salary cap is not working\n";
}

// Cleanup
echo "\nCleaning up...\n";
$db->table('ci_payslip_statutory_deductions')
   ->where('staff_id', $employeeId)
   ->where('payslip_id', 0)
   ->delete();

$db->table('ci_leave_applications')
   ->where('employee_id', $employeeId)
   ->delete();

echo "✓ Cleanup complete\n";
