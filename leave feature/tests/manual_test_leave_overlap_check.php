<?php

/**
 * Manual Test: Leave Overlap Check
 * 
 * This test verifies that the system prevents overlapping leave requests
 * regardless of leave type.
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

echo "=== Leave Overlap Check Test ===\n\n";

// Test employee data
$employeeId = 999992;

echo "Test Setup:\n";
echo "- Employee ID: {$employeeId}\n\n";

// Clean up any existing test data
$db->table('ci_leave_applications')->where('employee_id', $employeeId)->delete();
$db->table('ci_erp_users_details')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_users')->where('user_id', $employeeId)->delete();

// Create test employee
$userData = [
    'user_id' => $employeeId,
    'company_id' => 1,
    'first_name' => 'Test',
    'last_name' => 'Overlap',
    'email' => 'test' . $employeeId . '@example.com',
    'username' => 'testuser' . $employeeId,
    'is_active' => 1,
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_erp_users')->insert($userData);

$staffData = [
    'user_id' => $employeeId,
    'company_id' => 1,
    'basic_salary' => 10000,
    'employee_id' => 'EMP' . $employeeId,
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_erp_users_details')->insert($staffData);

echo "Test employee created.\n\n";

// Create first leave application (Annual Leave)
$leave1Data = [
    'employee_id' => $employeeId,
    'company_id' => 1,
    'leave_type_id' => 1, // Annual leave
    'from_date' => '2026-03-10',
    'to_date' => '2026-03-15',
    'calculated_days' => 6,
    'country_code' => 'SA',
    'status' => 1, // Approved
    'created_at' => date('Y-m-d H:i:s')
];
$db->table('ci_leave_applications')->insert($leave1Data);
$leave1Id = $db->insertID();

echo "First Leave Created:\n";
echo "- Type: Annual Leave (ID: 1)\n";
echo "- From: 2026-03-10\n";
echo "- To: 2026-03-15\n";
echo "- Status: Approved\n\n";

// Test Case 1: Try to create overlapping leave (Sick Leave) - should be blocked
echo "=== Test Case 1: Overlapping Leave (Different Type) ===\n";
echo "Attempting to create Sick Leave from 2026-03-12 to 2026-03-18...\n";

$builder = $db->table('ci_leave_applications');
$builder->where('employee_id', $employeeId);
$builder->where('status!=', 3); // Exclude rejected
$builder->groupStart()
    ->where("from_date BETWEEN '2026-03-12' AND '2026-03-18'")
    ->orWhere("to_date BETWEEN '2026-03-12' AND '2026-03-18'")
    ->orWhere("(from_date <= '2026-03-12' AND to_date >= '2026-03-18')")
->groupEnd();
$query = $builder->get();
$overlappingLeaves = $query->getResultArray();

if (count($overlappingLeaves) > 0) {
    echo "✓ PASS: Overlap detected! System would block this request.\n";
    echo "  Existing leave: " . $overlappingLeaves[0]['from_date'] . " to " . $overlappingLeaves[0]['to_date'] . "\n";
} else {
    echo "✗ FAIL: No overlap detected. System would allow this request.\n";
}
echo "\n";

// Test Case 2: Try to create non-overlapping leave - should be allowed
echo "=== Test Case 2: Non-Overlapping Leave ===\n";
echo "Attempting to create Sick Leave from 2026-03-20 to 2026-03-25...\n";

$builder = $db->table('ci_leave_applications');
$builder->where('employee_id', $employeeId);
$builder->where('status!=', 3);
$builder->groupStart()
    ->where("from_date BETWEEN '2026-03-20' AND '2026-03-25'")
    ->orWhere("to_date BETWEEN '2026-03-20' AND '2026-03-25'")
    ->orWhere("(from_date <= '2026-03-20' AND to_date >= '2026-03-25')")
->groupEnd();
$query = $builder->get();
$overlappingLeaves = $query->getResultArray();

if (count($overlappingLeaves) === 0) {
    echo "✓ PASS: No overlap detected. System would allow this request.\n";
} else {
    echo "✗ FAIL: Overlap detected. System would block this valid request.\n";
}
echo "\n";

// Test Case 3: Exact same dates - should be blocked
echo "=== Test Case 3: Exact Same Dates ===\n";
echo "Attempting to create Sick Leave from 2026-03-10 to 2026-03-15...\n";

$builder = $db->table('ci_leave_applications');
$builder->where('employee_id', $employeeId);
$builder->where('status!=', 3);
$builder->groupStart()
    ->where("from_date BETWEEN '2026-03-10' AND '2026-03-15'")
    ->orWhere("to_date BETWEEN '2026-03-10' AND '2026-03-15'")
    ->orWhere("(from_date <= '2026-03-10' AND to_date >= '2026-03-15')")
->groupEnd();
$query = $builder->get();
$overlappingLeaves = $query->getResultArray();

if (count($overlappingLeaves) > 0) {
    echo "✓ PASS: Overlap detected! System would block this request.\n";
} else {
    echo "✗ FAIL: No overlap detected. System would allow this duplicate request.\n";
}
echo "\n";

// Test Case 4: Partial overlap (start date within existing leave) - should be blocked
echo "=== Test Case 4: Partial Overlap (Start Date) ===\n";
echo "Attempting to create leave from 2026-03-14 to 2026-03-20...\n";

$builder = $db->table('ci_leave_applications');
$builder->where('employee_id', $employeeId);
$builder->where('status!=', 3);
$builder->groupStart()
    ->where("from_date BETWEEN '2026-03-14' AND '2026-03-20'")
    ->orWhere("to_date BETWEEN '2026-03-14' AND '2026-03-20'")
    ->orWhere("(from_date <= '2026-03-14' AND to_date >= '2026-03-20')")
->groupEnd();
$query = $builder->get();
$overlappingLeaves = $query->getResultArray();

if (count($overlappingLeaves) > 0) {
    echo "✓ PASS: Overlap detected! System would block this request.\n";
} else {
    echo "✗ FAIL: No overlap detected. System would allow this overlapping request.\n";
}
echo "\n";

// Test Case 5: Encompassing leave (new leave contains existing leave) - should be blocked
echo "=== Test Case 5: Encompassing Leave ===\n";
echo "Attempting to create leave from 2026-03-05 to 2026-03-20...\n";

$builder = $db->table('ci_leave_applications');
$builder->where('employee_id', $employeeId);
$builder->where('status!=', 3);
$builder->groupStart()
    ->where("from_date BETWEEN '2026-03-05' AND '2026-03-20'")
    ->orWhere("to_date BETWEEN '2026-03-05' AND '2026-03-20'")
    ->orWhere("(from_date <= '2026-03-05' AND to_date >= '2026-03-20')")
->groupEnd();
$query = $builder->get();
$overlappingLeaves = $query->getResultArray();

if (count($overlappingLeaves) > 0) {
    echo "✓ PASS: Overlap detected! System would block this request.\n";
} else {
    echo "✗ FAIL: No overlap detected. System would allow this overlapping request.\n";
}
echo "\n";

// Clean up
echo "=== Cleanup ===\n";
$db->table('ci_leave_applications')->where('employee_id', $employeeId)->delete();
$db->table('ci_erp_users_details')->where('user_id', $employeeId)->delete();
$db->table('ci_erp_users')->where('user_id', $employeeId)->delete();
echo "Test data cleaned up.\n";

echo "\n=== Test Complete ===\n";
