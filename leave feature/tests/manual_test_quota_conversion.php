<?php

/**
 * Manual Test Script: Leave Quota Conversion (Days to Hours)
 * 
 * This script tests the convertQuotaDaysToHours() method to ensure
 * leave quotas are properly converted from days to hours based on
 * employee's shift configuration.
 * 
 * Run from command line:
 * php tests/manual_test_quota_conversion.php
 */

// Load CodeIgniter
require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap CodeIgniter
$app = require_once __DIR__ . '/../app/Config/Paths.php';
$paths = new Config\Paths();
require_once SYSTEMPATH . 'bootstrap.php';

// Initialize database
$db = \Config\Database::connect();

// Load LeavePolicy library
$LeavePolicy = new \App\Libraries\LeavePolicy();

echo "=================================================\n";
echo "Leave Quota Conversion Test\n";
echo "=================================================\n\n";

// Test Case 1: Employee with 8-hour shift
echo "Test Case 1: Employee with 8-hour shift\n";
echo "----------------------------------------\n";

// Find an employee with 8-hour shift
$employee8h = $db->query("
    SELECT u.user_id, u.first_name, u.last_name, s.hours_per_day
    FROM ci_erp_users u
    JOIN ci_erp_users_details ud ON u.user_id = ud.user_id
    JOIN ci_office_shifts s ON ud.office_shift_id = s.office_shift_id
    WHERE s.hours_per_day = 8
    LIMIT 1
")->getRow();

if ($employee8h) {
    $quotaDays = 21;
    $expectedHours = 168; // 21 × 8
    $actualHours = $LeavePolicy->convertQuotaDaysToHours($employee8h->user_id, $quotaDays);
    
    echo "Employee: {$employee8h->first_name} {$employee8h->last_name} (ID: {$employee8h->user_id})\n";
    echo "Shift: {$employee8h->hours_per_day} hours/day\n";
    echo "Quota: {$quotaDays} days\n";
    echo "Expected: {$expectedHours} hours\n";
    echo "Actual: {$actualHours} hours\n";
    echo "Status: " . ($actualHours == $expectedHours ? "✓ PASS" : "✗ FAIL") . "\n\n";
} else {
    echo "No employee found with 8-hour shift\n\n";
}

// Test Case 2: Employee with 10-hour shift
echo "Test Case 2: Employee with 10-hour shift\n";
echo "----------------------------------------\n";

$employee10h = $db->query("
    SELECT u.user_id, u.first_name, u.last_name, s.hours_per_day
    FROM ci_erp_users u
    JOIN ci_erp_users_details ud ON u.user_id = ud.user_id
    JOIN ci_office_shifts s ON ud.office_shift_id = s.office_shift_id
    WHERE s.hours_per_day = 10
    LIMIT 1
")->getRow();

if ($employee10h) {
    $quotaDays = 21;
    $expectedHours = 210; // 21 × 10
    $actualHours = $LeavePolicy->convertQuotaDaysToHours($employee10h->user_id, $quotaDays);
    
    echo "Employee: {$employee10h->first_name} {$employee10h->last_name} (ID: {$employee10h->user_id})\n";
    echo "Shift: {$employee10h->hours_per_day} hours/day\n";
    echo "Quota: {$quotaDays} days\n";
    echo "Expected: {$expectedHours} hours\n";
    echo "Actual: {$actualHours} hours\n";
    echo "Status: " . ($actualHours == $expectedHours ? "✓ PASS" : "✗ FAIL") . "\n\n";
} else {
    echo "No employee found with 10-hour shift\n\n";
}

// Test Case 3: Employee with 12-hour shift
echo "Test Case 3: Employee with 12-hour shift\n";
echo "----------------------------------------\n";

$employee12h = $db->query("
    SELECT u.user_id, u.first_name, u.last_name, s.hours_per_day
    FROM ci_erp_users u
    JOIN ci_erp_users_details ud ON u.user_id = ud.user_id
    JOIN ci_office_shifts s ON ud.office_shift_id = s.office_shift_id
    WHERE s.hours_per_day = 12
    LIMIT 1
")->getRow();

if ($employee12h) {
    $quotaDays = 21;
    $expectedHours = 252; // 21 × 12
    $actualHours = $LeavePolicy->convertQuotaDaysToHours($employee12h->user_id, $quotaDays);
    
    echo "Employee: {$employee12h->first_name} {$employee12h->last_name} (ID: {$employee12h->user_id})\n";
    echo "Shift: {$employee12h->hours_per_day} hours/day\n";
    echo "Quota: {$quotaDays} days\n";
    echo "Expected: {$expectedHours} hours\n";
    echo "Actual: {$actualHours} hours\n";
    echo "Status: " . ($actualHours == $expectedHours ? "✓ PASS" : "✗ FAIL") . "\n\n";
} else {
    echo "No employee found with 12-hour shift\n\n";
}

// Test Case 4: formatHoursBalanceDisplay
echo "Test Case 4: Format Hours Balance Display\n";
echo "----------------------------------------\n";

if ($employee8h) {
    $hoursBalance = 88; // 11 days for 8-hour shift
    $formatted = $LeavePolicy->formatHoursBalanceDisplay($employee8h->user_id, $hoursBalance);
    $expected = "11.00 days (88.00 hours)";
    
    echo "Employee: {$employee8h->first_name} {$employee8h->last_name} (ID: {$employee8h->user_id})\n";
    echo "Shift: {$employee8h->hours_per_day} hours/day\n";
    echo "Balance: {$hoursBalance} hours\n";
    echo "Expected: {$expected}\n";
    echo "Actual: {$formatted}\n";
    echo "Status: " . ($formatted == $expected ? "✓ PASS" : "✗ FAIL") . "\n\n";
}

// Test Case 5: Different quota amounts
echo "Test Case 5: Different Quota Amounts\n";
echo "----------------------------------------\n";

if ($employee8h) {
    $testCases = [
        ['days' => 15, 'expected' => 120],  // 15 × 8
        ['days' => 30, 'expected' => 240],  // 30 × 8
        ['days' => 7, 'expected' => 56],    // 7 × 8
        ['days' => 0, 'expected' => 0],     // 0 × 8
    ];
    
    echo "Employee: {$employee8h->first_name} {$employee8h->last_name} (ID: {$employee8h->user_id})\n";
    echo "Shift: {$employee8h->hours_per_day} hours/day\n\n";
    
    $allPassed = true;
    foreach ($testCases as $test) {
        $actual = $LeavePolicy->convertQuotaDaysToHours($employee8h->user_id, $test['days']);
        $passed = ($actual == $test['expected']);
        $allPassed = $allPassed && $passed;
        
        echo "  {$test['days']} days → {$actual} hours (expected: {$test['expected']}) ";
        echo $passed ? "✓" : "✗";
        echo "\n";
    }
    
    echo "\nOverall: " . ($allPassed ? "✓ ALL PASS" : "✗ SOME FAILED") . "\n\n";
}

// Summary
echo "=================================================\n";
echo "Test Summary\n";
echo "=================================================\n";
echo "All tests completed. Review results above.\n";
echo "\nKey Points:\n";
echo "- Quota in days is converted to hours based on shift\n";
echo "- Different shifts (8h, 10h, 12h) produce different hours\n";
echo "- Format method shows balance in both days and hours\n";
echo "- Zero quota correctly converts to zero hours\n";
echo "\n";
