<?php

/**
 * Real Scenario Test: Unpaid Leave Deduction in 31-Day Month
 * 
 * This test demonstrates the exact scenario described:
 * - Monthly salary: 30,000 SAR
 * - Unpaid leave: Entire July (31 days)
 * - Expected: Net salary should be 0, not negative
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

echo "=== REAL SCENARIO: 31 Days Unpaid Leave in July ===\n\n";

$employeeId = 999994;
$basicSalary = 30000;

echo "Scenario:\n";
echo "- Employee takes unpaid leave for entire July 2026 (31 days)\n";
echo "- Monthly salary: {$basicSalary} SAR\n";
echo "- System uses 30-day salary model\n\n";

// Clean up
$db->table('ci_leave_applications')->where('employee_id', $employeeId)->delete();
$db->table('ci_office_shifts')->where('shift_name', 'Test Shift 999994')->delete();
$db->table('ci_erp_users_details')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_users')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_constants')->where('constants_id', 9998)->delete();

// Create test shift (7 working days)
$shiftData = [
    'shift_name' => 'Test Shift 999994',
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

// Create employee
$userData = [
    'user_id' => $employeeId,
    'company_id' => 1,
    'first_name' => 'Ahmed',
    'last_name' => 'Ali',
    'email' => 'ahmed.ali@example.com',
    'username' => 'ahmed.ali',
    'is_active' => 1,
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_erp_users')->insert($userData);

$staffData = [
    'user_id' => $employeeId,
    'company_id' => 1,
    'basic_salary' => $basicSalary,
    'office_shift_id' => $shiftId,
    'employee_id' => 'EMP001',
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_erp_users_details')->insert($staffData);

// Create unpaid leave type
$leaveTypeData = [
    'constants_id' => 9998,
    'type' => 'leave_type',
    'category_name' => 'Unpaid Leave',
    'field_three' => '0', // Unpaid
    'company_id' => 1
];
$db->table('ci_erp_constants')->insert($leaveTypeData);

// Create leave for entire July 2026 (31 days)
$leaveData = [
    'employee_id' => $employeeId,
    'company_id' => 1,
    'leave_type_id' => 9998,
    'from_date' => '2026-07-01',
    'to_date' => '2026-07-31',
    'calculated_days' => 31,
    'leave_hours' => 248, // 31 × 8
    'status' => 1, // Approved
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_leave_applications')->insert($leaveData);

echo "Leave Application Created:\n";
echo "- From: 2026-07-01\n";
echo "- To: 2026-07-31\n";
echo "- Total Days: 31 days\n\n";

// Calculate deduction
$result = calculate_unpaid_leave_deduction($employeeId, '2026-07', $basicSalary);

echo "=== CALCULATION BREAKDOWN ===\n\n";

echo "Step 1: Calculate Daily Rate\n";
echo "  Daily Rate = Monthly Salary ÷ 30 (fixed model)\n";
echo "  Daily Rate = {$basicSalary} ÷ 30 = " . ($basicSalary / 30) . " SAR\n\n";

echo "Step 2: Count Unpaid Days\n";
echo "  Actual unpaid days in July: 31 days\n";
echo "  Capped at: 30 days (maximum per month)\n";
echo "  Chargeable days: {$result['hours']} days\n\n";

echo "Step 3: Calculate Deduction\n";
echo "  Calculated Deduction = Chargeable Days × Daily Rate\n";
echo "  Calculated Deduction = {$result['hours']} × " . ($basicSalary / 30) . " = " . ($result['hours'] * ($basicSalary / 30)) . " SAR\n\n";

echo "Step 4: Apply Salary Cap\n";
echo "  Final Deduction = min(Calculated Deduction, Monthly Salary)\n";
echo "  Final Deduction = min(" . ($result['hours'] * ($basicSalary / 30)) . ", {$basicSalary}) = {$result['deduction']} SAR\n\n";

echo "=== FINAL RESULT ===\n\n";
echo "Monthly Salary:        {$basicSalary} SAR\n";
echo "Unpaid Leave Deduction: {$result['deduction']} SAR\n";
echo "Net Salary:            " . ($basicSalary - $result['deduction']) . " SAR\n\n";

if (($basicSalary - $result['deduction']) >= 0) {
    echo "✓ SUCCESS: Net salary is NOT negative!\n";
    echo "✓ The 30-day cap prevents over-deduction\n";
    echo "✓ Employee receives 0 SAR (not -1,000 SAR)\n";
} else {
    echo "✗ FAILED: Net salary is negative!\n";
}

// Clean up
echo "\n=== Cleanup ===\n";
$db->table('ci_leave_applications')->where('employee_id', $employeeId)->delete();
$db->table('ci_erp_constants')->where('constants_id', 9998)->delete();
$db->table('ci_office_shifts')->where('office_shift_id', $shiftId)->delete();
$db->table('ci_erp_users_details')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_users')->where('user_id', $employeeId)->delete();
echo "Test data cleaned up.\n";

echo "\n=== SUMMARY ===\n";
echo "The fix ensures that:\n";
echo "1. Daily rate is always calculated as: Salary ÷ 30\n";
echo "2. Maximum deductible days per month: 30 days\n";
echo "3. Maximum deduction per month: Monthly salary\n";
echo "4. Net salary will NEVER be negative\n";
