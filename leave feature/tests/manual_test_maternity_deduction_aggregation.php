<?php

/**
 * Manual Test: Maternity Leave Deduction Aggregation
 * 
 * This test verifies that the createMaternityLeaveDeductions() method
 * correctly aggregates tier segments into single monthly records.
 * 
 * Test Case: 77-day maternity leave from 2026-03-01 to 2026-05-16
 * - Days 1-70: Full pay (no deduction)
 * - Days 71-77: 100% deduction (7 days in May)
 * - Expected: One deduction record in May with 7 days × daily_rate
 */

// Change to project root
chdir(__DIR__ . '/..');

// Load CodeIgniter
define('FCPATH', __DIR__ . '/../public/');
require_once __DIR__ . '/../app/Config/Paths.php';
$paths = new Config\Paths();
require_once FCPATH . '../system/bootstrap.php';

$app = Config\Services::codeigniter();
$app->initialize();

$db = \Config\Database::connect();
$leavePolicy = new \App\Libraries\LeavePolicy();

echo "=== Maternity Leave Deduction Aggregation Test ===\n\n";

// Test employee data
$employeeId = 999991;
$basicSalary = 10000;
$dailyRate = $basicSalary / 30;

echo "Test Setup:\n";
echo "- Employee ID: {$employeeId}\n";
echo "- Basic Salary: {$basicSalary} SAR\n";
echo "- Daily Rate: {$dailyRate} SAR\n\n";

// Clean up any existing test data
$db->table('ci_payslip_statutory_deductions')->where('staff_id', $employeeId)->delete();
$db->table('ci_leave_applications')->where('employee_id', $employeeId)->delete();
$db->table('ci_erp_users_details')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_users')->where('user_id', $employeeId)->delete();

// Create test employee
$userData = [
    'user_id' => $employeeId,
    'company_id' => 1,
    'first_name' => 'Test',
    'last_name' => 'Employee',
    'email' => 'test' . $employeeId . '@example.com',
    'username' => 'testuser' . $employeeId,
    'is_active' => 1,
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_erp_users')->insert($userData);

$staffData = [
    'user_id' => $employeeId,
    'company_id' => 1,
    'basic_salary' => $basicSalary,
    'employee_id' => 'EMP' . $employeeId,
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_erp_users_details')->insert($staffData);

echo "Test employee created.\n\n";

// Create test leave application: 77 days from 2026-03-01 to 2026-05-16
$leaveData = [
    'employee_id' => $employeeId,
    'company_id' => 1,
    'leave_type_id' => 3, // Maternity leave
    'from_date' => '2026-03-01',
    'to_date' => '2026-05-16',
    'calculated_days' => 77,
    'country_code' => 'SA',
    'status' => 1, // Approved
    'salary_deduction_applied' => 0,
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_leave_applications')->insert($leaveData);
$leaveId = $db->insertID();

echo "Test Case: 77-day maternity leave\n";
echo "- From: 2026-03-01\n";
echo "- To: 2026-05-16\n";
echo "- Days: 77\n";
echo "- Country: SA (Saudi Arabia)\n\n";

echo "Expected Deductions:\n";
echo "- March 2026: 0.00 SAR (Days 1-31, all in Tier 1: 100% pay)\n";
echo "- April 2026: 0.00 SAR (Days 32-61, all in Tier 1: 100% pay)\n";
echo "- May 2026: " . round(7 * $dailyRate, 2) . " SAR (Days 71-77, 7 days in Tier 2: 0% pay)\n\n";

// Call createMaternityLeaveDeductions
echo "Calling createMaternityLeaveDeductions()...\n";
$result = $leavePolicy->createMaternityLeaveDeductions($leaveId);
echo "Result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n\n";

// Query deductions from database
echo "Querying deductions from database...\n\n";

$months = ['2026-03', '2026-04', '2026-05'];
$totalDeduction = 0;

foreach ($months as $month) {
    $deductions = $db->table('ci_payslip_statutory_deductions')
        ->where('staff_id', $employeeId)
        ->where('salary_month', $month)
        ->where('payslip_id', 0)
        ->where('contract_option_id', 0)
        ->like('pay_title', 'Maternity', 'both')
        ->get()->getResultArray();
    
    echo "Month: {$month}\n";
    echo "- Number of records: " . count($deductions) . "\n";
    
    if (count($deductions) > 0) {
        foreach ($deductions as $deduction) {
            echo "  - Title: {$deduction['pay_title']}\n";
            echo "  - Amount: {$deduction['pay_amount']} SAR\n";
            $totalDeduction += $deduction['pay_amount'];
        }
    } else {
        echo "  - No deductions\n";
    }
    echo "\n";
}

echo "Total Deduction: {$totalDeduction} SAR\n";
echo "Expected Total: " . round(7 * $dailyRate, 2) . " SAR\n\n";

// Verify aggregation
echo "=== Verification ===\n";
$mayDeductions = $db->table('ci_payslip_statutory_deductions')
    ->where('staff_id', $employeeId)
    ->where('salary_month', '2026-05')
    ->where('payslip_id', 0)
    ->where('contract_option_id', 0)
    ->like('pay_title', 'Maternity', 'both')
    ->get()->getResultArray();

if (count($mayDeductions) === 1) {
    echo "✓ PASS: Exactly ONE record created for May (aggregated)\n";
    $deduction = $mayDeductions[0];
    
    if ($deduction['pay_title'] === 'Maternity Leave Deduction') {
        echo "✓ PASS: Title is simple (no tier details)\n";
    } else {
        echo "✗ FAIL: Title contains tier details: {$deduction['pay_title']}\n";
    }
    
    $expectedAmount = round(7 * $dailyRate, 2);
    $actualAmount = $deduction['pay_amount'];
    
    if (abs($expectedAmount - $actualAmount) < 0.02) {
        echo "✓ PASS: Amount is correct ({$actualAmount} SAR)\n";
    } else {
        echo "✗ FAIL: Amount is incorrect. Expected {$expectedAmount}, got {$actualAmount}\n";
    }
} else {
    echo "✗ FAIL: Expected 1 record for May, found " . count($mayDeductions) . "\n";
    if (count($mayDeductions) > 1) {
        echo "  This indicates the bug is NOT fixed - multiple records per month\n";
    }
}

// Clean up
echo "\n=== Cleanup ===\n";
$db->table('ci_payslip_statutory_deductions')->where('staff_id', $employeeId)->delete();
$db->table('ci_leave_applications')->where('leave_id', $leaveId)->delete();
$db->table('ci_erp_users_details')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_users')->where('user_id', $employeeId)->delete();
echo "Test data cleaned up.\n";

echo "\n=== Test Complete ===\n";
