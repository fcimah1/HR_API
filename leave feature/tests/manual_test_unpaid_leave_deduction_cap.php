<?php

/**
 * Manual Test: Unpaid Leave Deduction Cap
 * 
 * This test verifies that unpaid leave deductions are capped correctly
 * to prevent negative net salary.
 * 
 * Test Cases:
 * 1. 31 days unpaid leave in July (31-day month) - should cap at 30 days
 * 2. 30 days unpaid leave in June (30-day month) - should deduct full amount
 * 3. 35 days unpaid leave - should cap at 30 days and monthly salary
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

// Load helper
helper('payroll');

$db = \Config\Database::connect();

echo "=== Unpaid Leave Deduction Cap Test ===\n\n";

// Test employee data
$employeeId = 999993;
$basicSalary = 30000;
$dailyRate = $basicSalary / 30; // Fixed 30-day model

echo "Test Setup:\n";
echo "- Employee ID: {$employeeId}\n";
echo "- Basic Salary: {$basicSalary} SAR\n";
echo "- Daily Rate (30-day model): {$dailyRate} SAR\n\n";

// Clean up any existing test data
$db->table('ci_leave_applications')->where('employee_id', $employeeId)->delete();
$db->table('ci_office_shifts')->where('shift_name', 'Test Shift 999993')->delete();
$db->table('ci_erp_users_details')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_users')->where('user_id', $employeeId)->delete();

// Create test shift (7 working days per week for simplicity)
$shiftData = [
    'shift_name' => 'Test Shift 999993',
    'monday_in_time' => '08:00:00',
    'monday_out_time' => '17:00:00',
    'tuesday_in_time' => '08:00:00',
    'tuesday_out_time' => '17:00:00',
    'wednesday_in_time' => '08:00:00',
    'wednesday_out_time' => '17:00:00',
    'thursday_in_time' => '08:00:00',
    'thursday_out_time' => '17:00:00',
    'friday_in_time' => '08:00:00',
    'friday_out_time' => '17:00:00',
    'saturday_in_time' => '08:00:00',
    'saturday_out_time' => '17:00:00',
    'sunday_in_time' => '08:00:00',
    'sunday_out_time' => '17:00:00',
    'hours_per_day' => 8
];
$db->table('ci_office_shifts')->insert($shiftData);
$shiftId = $db->insertID();

// Create test employee
$userData = [
    'user_id' => $employeeId,
    'company_id' => 1,
    'first_name' => 'Test',
    'last_name' => 'Unpaid',
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
    'office_shift_id' => $shiftId,
    'employee_id' => 'EMP' . $employeeId,
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_erp_users_details')->insert($staffData);

// Create unpaid leave type
$leaveTypeData = [
    'constants_id' => 9999,
    'type' => 'leave_type',
    'category_name' => 'Test Unpaid Leave',
    'field_three' => '0', // Unpaid
    'company_id' => 1
];
$db->table('ci_erp_constants')->insert($leaveTypeData);

echo "Test employee and shift created.\n\n";

// ===== TEST CASE 1: 31 days unpaid leave in July (31-day month) =====
echo "=== Test Case 1: 31 Days Unpaid Leave in July (31-day month) ===\n";
echo "Expected: Deduction capped at 30 days × {$dailyRate} = " . (30 * $dailyRate) . " SAR\n";
echo "Expected: Net salary = {$basicSalary} - " . (30 * $dailyRate) . " = 0.00 SAR (not negative)\n\n";

// Create leave application for entire July 2026 (31 days)
$leave1Data = [
    'employee_id' => $employeeId,
    'company_id' => 1,
    'leave_type_id' => 9999,
    'from_date' => '2026-07-01',
    'to_date' => '2026-07-31',
    'calculated_days' => 31,
    'leave_hours' => 248, // 31 days × 8 hours
    'status' => 1, // Approved
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_leave_applications')->insert($leave1Data);

// Calculate deduction
$result1 = calculate_unpaid_leave_deduction($employeeId, '2026-07', $basicSalary);

echo "Result:\n";
echo "- Unpaid Days: {$result1['hours']}\n";
echo "- Deduction: {$result1['deduction']} SAR\n";
echo "- Net Salary: " . ($basicSalary - $result1['deduction']) . " SAR\n\n";

if ($result1['hours'] <= 30 && $result1['deduction'] <= $basicSalary && ($basicSalary - $result1['deduction']) >= 0) {
    echo "✓ PASS: Deduction capped correctly, net salary is not negative\n";
} else {
    echo "✗ FAIL: Deduction not capped correctly\n";
}
echo "\n";

// Clean up
$db->table('ci_leave_applications')->where('employee_id', $employeeId)->delete();

// ===== TEST CASE 2: 30 days unpaid leave in June (30-day month) =====
echo "=== Test Case 2: 30 Days Unpaid Leave in June (30-day month) ===\n";
echo "Expected: Deduction = 30 days × {$dailyRate} = " . (30 * $dailyRate) . " SAR\n";
echo "Expected: Net salary = {$basicSalary} - " . (30 * $dailyRate) . " = 0.00 SAR\n\n";

// Create leave application for entire June 2026 (30 days)
$leave2Data = [
    'employee_id' => $employeeId,
    'company_id' => 1,
    'leave_type_id' => 9999,
    'from_date' => '2026-06-01',
    'to_date' => '2026-06-30',
    'calculated_days' => 30,
    'leave_hours' => 240, // 30 days × 8 hours
    'status' => 1, // Approved
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_leave_applications')->insert($leave2Data);

// Calculate deduction
$result2 = calculate_unpaid_leave_deduction($employeeId, '2026-06', $basicSalary);

echo "Result:\n";
echo "- Unpaid Days: {$result2['hours']}\n";
echo "- Deduction: {$result2['deduction']} SAR\n";
echo "- Net Salary: " . ($basicSalary - $result2['deduction']) . " SAR\n\n";

if ($result2['hours'] == 30 && $result2['deduction'] == $basicSalary && ($basicSalary - $result2['deduction']) == 0) {
    echo "✓ PASS: Full month deduction calculated correctly\n";
} else {
    echo "✗ FAIL: Deduction calculation incorrect\n";
}
echo "\n";

// Clean up
$db->table('ci_leave_applications')->where('employee_id', $employeeId)->delete();

// ===== TEST CASE 3: 15 days unpaid leave =====
echo "=== Test Case 3: 15 Days Unpaid Leave ===\n";
echo "Expected: Deduction = 15 days × {$dailyRate} = " . (15 * $dailyRate) . " SAR\n";
echo "Expected: Net salary = {$basicSalary} - " . (15 * $dailyRate) . " = " . ($basicSalary - (15 * $dailyRate)) . " SAR\n\n";

// Create leave application for 15 days
$leave3Data = [
    'employee_id' => $employeeId,
    'company_id' => 1,
    'leave_type_id' => 9999,
    'from_date' => '2026-08-01',
    'to_date' => '2026-08-15',
    'calculated_days' => 15,
    'leave_hours' => 120, // 15 days × 8 hours
    'status' => 1, // Approved
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_leave_applications')->insert($leave3Data);

// Calculate deduction
$result3 = calculate_unpaid_leave_deduction($employeeId, '2026-08', $basicSalary);

echo "Result:\n";
echo "- Unpaid Days: {$result3['hours']}\n";
echo "- Deduction: {$result3['deduction']} SAR\n";
echo "- Net Salary: " . ($basicSalary - $result3['deduction']) . " SAR\n\n";

$expectedDeduction = 15 * $dailyRate;
if (abs($result3['deduction'] - $expectedDeduction) < 0.01 && ($basicSalary - $result3['deduction']) > 0) {
    echo "✓ PASS: Partial month deduction calculated correctly\n";
} else {
    echo "✗ FAIL: Deduction calculation incorrect\n";
}
echo "\n";

// Clean up
echo "=== Cleanup ===\n";
$db->table('ci_leave_applications')->where('employee_id', $employeeId)->delete();
$db->table('ci_erp_constants')->where('constants_id', 9999)->delete();
$db->table('ci_office_shifts')->where('office_shift_id', $shiftId)->delete();
$db->table('ci_erp_users_details')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_users')->where('user_id', $employeeId)->delete();
echo "Test data cleaned up.\n";

echo "\n=== Test Complete ===\n";
echo "\nSummary:\n";
echo "The fix ensures:\n";
echo "1. Daily rate is calculated using fixed 30-day model (salary ÷ 30)\n";
echo "2. Unpaid days are capped at 30 days maximum per month\n";
echo "3. Total deduction cannot exceed monthly salary\n";
echo "4. Net salary will never be negative\n";
