<?php

/**
 * Manual Test Script for getEmployeeShiftData() method
 * 
 * This script tests the newly implemented getEmployeeShiftData() method
 * to ensure it correctly retrieves shift data for employees.
 * 
 * Run this from the command line:
 * php tests/manual_test_getEmployeeShiftData.php
 */

// Load CodeIgniter
chdir(__DIR__ . '/..');
require_once 'vendor/autoload.php';

// Define path constants
define('ROOTPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', ROOTPATH . 'system' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public' . DIRECTORY_SEPARATOR);
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);

// Bootstrap CodeIgniter
require_once SYSTEMPATH . 'bootstrap.php';

$app = Config\Services::codeigniter();
$app->initialize();

// Get database instance
$db = \Config\Database::connect();

echo "=== Testing getEmployeeShiftData() Method ===\n\n";

// Create LeavePolicy instance
$leavePolicy = new \App\Libraries\LeavePolicy();

// Test 1: Employee with no shift assigned (should return null)
echo "Test 1: Employee with no shift assigned\n";
echo "---------------------------------------\n";
$result = $leavePolicy->getEmployeeShiftData(999999); // Non-existent employee
echo "Result: " . ($result === null ? "NULL (PASS)" : "NOT NULL (FAIL)") . "\n\n";

// Test 2: Find an employee with a shift assigned
echo "Test 2: Employee with shift assigned\n";
echo "-------------------------------------\n";

// Query for an employee with a shift
$query = $db->query("
    SELECT user_id, office_shift_id 
    FROM ci_erp_users_details 
    WHERE office_shift_id IS NOT NULL 
    AND office_shift_id > 0 
    LIMIT 1
");

$employee = $query->getRow();

if ($employee) {
    echo "Testing with Employee ID: {$employee->user_id}, Shift ID: {$employee->office_shift_id}\n";
    
    $shiftData = $leavePolicy->getEmployeeShiftData($employee->user_id);
    
    if ($shiftData === null) {
        echo "Result: NULL (FAIL - Expected shift data)\n\n";
    } else {
        echo "Result: Shift data retrieved successfully (PASS)\n";
        echo "Shift ID: " . ($shiftData->office_shift_id ?? 'N/A') . "\n";
        echo "Hours per day: " . ($shiftData->hours_per_day ?? 'N/A') . "\n";
        echo "Monday in time: " . ($shiftData->monday_in_time ?? 'N/A') . "\n";
        echo "Monday out time: " . ($shiftData->monday_out_time ?? 'N/A') . "\n";
        
        // Verify all day columns exist
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $allDaysPresent = true;
        
        foreach ($daysOfWeek as $day) {
            if (!property_exists($shiftData, "{$day}_in_time")) {
                echo "Missing property: {$day}_in_time\n";
                $allDaysPresent = false;
            }
        }
        
        if ($allDaysPresent) {
            echo "\nAll day columns present: YES (PASS)\n";
        } else {
            echo "\nAll day columns present: NO (FAIL)\n";
        }
    }
} else {
    echo "No employees with shifts found in database\n";
}

echo "\n=== Test Complete ===\n";
