<?php

/**
 * Complete Workflow Test: Unpaid Leave from Creation to Payroll
 * 
 * This test simulates the complete workflow:
 * 1. Create employee
 * 2. Create unpaid leave for 31 days in July
 * 3. Calculate payroll deduction
 * 4. Verify net salary is not negative
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

echo "=== COMPLETE WORKFLOW TEST: Unpaid Leave ===\n\n";

$employeeId = 999995;
$basicSalary = 30000;

echo "SCENARIO:\n";
echo "Employee takes 31 days unpaid leave in July 2026\n";
echo "Monthly Salary: {$basicSalary} SAR\n";
echo "Expected: Net salary should be 0 SAR (not negative)\n\n";

// Clean up
$db->table('ci_leave_applications')->where('employee_id', $employeeId)->delete();
$db->table('ci_office_shifts')->where('shift_name', 'Test Shift 999995')->delete();
$db->table('ci_erp_users_details')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_users')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_constants')->where('constants_id', 9997)->delete();

// Step 1: Create shift
echo "STEP 1: Creating employee shift...\n";
$shiftData = [
    'shift_name' => 'Test Shift 999995',
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
echo "✓ Shift created (ID: {$shiftId})\n\n";

// Step 2: Create employee
echo "STEP 2: Creating employee...\n";
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
    'office_shift_id' => $shiftId,
    'employee_id' => 'EMP' . $employeeId,
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_erp_users_details')->insert($staffData);
echo "✓ Employee created (ID: {$employeeId}, Salary: {$basicSalary} SAR)\n\n";

// Step 3: Create unpaid leave type
echo "STEP 3: Creating unpaid leave type...\n";
$leaveTypeData = [
    'constants_id' => 9997,
    'type' => 'leave_type',
    'category_name' => 'Unpaid Leave Test',
    'field_three' => '0', // Unpaid
    'company_id' => 1
];
$db->table('ci_erp_constants')->insert($leaveTypeData);
echo "✓ Leave type created (Unpaid)\n\n";

// Step 4: Create leave application for entire July (31 days)
echo "STEP 4: Creating leave application...\n";
echo "- From: 2026-07-01\n";
echo "- To: 2026-07-31\n";
echo "- Days: 31\n";

$leaveData = [
    'employee_id' => $employeeId,
    'company_id' => 1,
    'leave_type_id' => 9997,
    'from_date' => '2026-07-01',
    'to_date' => '2026-07-31',
    'calculated_days' => 31,
    'leave_hours' => 248, // 31 × 8 (THIS IS THE STORED VALUE)
    'status' => 1, // Approved
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_leave_applications')->insert($leaveData);
$leaveId = $db->insertID();
echo "✓ Leave created (ID: {$leaveId})\n";
echo "  Stored leave_hours: 248 (31 days × 8 hours)\n\n";

// Step 5: Calculate payroll deduction (this is what happens during payroll generation)
echo "STEP 5: Calculating payroll deduction for July 2026...\n";
$result = calculate_unpaid_leave_deduction($employeeId, '2026-07', $basicSalary);

echo "\nRESULTS:\n";
echo "- Unpaid Days (from function): {$result['hours']} days\n";
echo "- Daily Rate: " . ($basicSalary / 30) . " SAR\n";
echo "- Deduction Amount: {$result['deduction']} SAR\n";
echo "- Net Salary: " . ($basicSalary - $result['deduction']) . " SAR\n\n";

// Step 6: Verify
echo "VERIFICATION:\n";

$passed = true;

if ($result['hours'] <= 30) {
    echo "✓ Days capped at 30 (actual: {$result['hours']})\n";
} else {
    echo "✗ Days NOT capped (actual: {$result['hours']})\n";
    $passed = false;
}

if ($result['deduction'] <= $basicSalary) {
    echo "✓ Deduction capped at salary (actual: {$result['deduction']})\n";
} else {
    echo "✗ Deduction exceeds salary (actual: {$result['deduction']})\n";
    $passed = false;
}

$netSalary = $basicSalary - $result['deduction'];
if ($netSalary >= 0) {
    echo "✓ Net salary is NOT negative (actual: {$netSalary} SAR)\n";
} else {
    echo "✗ Net salary IS NEGATIVE (actual: {$netSalary} SAR)\n";
    $passed = false;
}

echo "\n";

if ($passed) {
    echo "=== TEST PASSED ===\n";
    echo "The fix is working correctly!\n";
    echo "Even though 248 hours (31 days) are stored in the database,\n";
    echo "the calculation function caps it at 30 days.\n";
} else {
    echo "=== TEST FAILED ===\n";
    echo "The fix is NOT working!\n";
    echo "Please check the calculate_unpaid_leave_deduction() function.\n";
}

// Clean up
echo "\n=== Cleanup ===\n";
$db->table('ci_leave_applications')->where('employee_id', $employeeId)->delete();
$db->table('ci_erp_constants')->where('constants_id', 9997)->delete();
$db->table('ci_office_shifts')->where('office_shift_id', $shiftId)->delete();
$db->table('ci_erp_users_details')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_users')->where('user_id', $employeeId)->delete();
echo "Test data cleaned up.\n";
