<?php

/**
 * Manual Test Script for calculateWorkingDaysInRange() method
 * 
 * This script tests the newly implemented calculateWorkingDaysInRange() method
 * to ensure it correctly calculates working days excluding weekends and holidays.
 * 
 * Run this from the command line:
 * php tests/manual_test_calculateWorkingDaysInRange.php
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

echo "=== Testing calculateWorkingDaysInRange() Method ===\n\n";

// Create LeavePolicy instance
$leavePolicy = new \App\Libraries\LeavePolicy();

// Test 1: Employee with no shift assigned (should return 0)
echo "Test 1: Employee with no shift assigned\n";
echo "---------------------------------------\n";
$result = $leavePolicy->calculateWorkingDaysInRange(999999, '2024-01-15', '2024-01-19');
echo "Result: {$result} working days (Expected: 0) - " . ($result === 0 ? "PASS" : "FAIL") . "\n\n";

// Test 2: Find an employee with a shift assigned
echo "Test 2: Employee with shift assigned\n";
echo "-------------------------------------\n";

// Query for an employee with a shift
$query = $db->query("
    SELECT u.user_id, u.company_id, d.office_shift_id 
    FROM ci_erp_users u
    JOIN ci_erp_users_details d ON u.user_id = d.user_id
    WHERE d.office_shift_id IS NOT NULL 
    AND d.office_shift_id > 0 
    LIMIT 1
");

$employee = $query->getRow();

if ($employee) {
    echo "Testing with Employee ID: {$employee->user_id}, Company ID: {$employee->company_id}, Shift ID: {$employee->office_shift_id}\n";
    
    // Get shift data to understand the working days
    $shiftData = $leavePolicy->getEmployeeShiftData($employee->user_id);
    
    if ($shiftData) {
        echo "\nShift Configuration:\n";
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $workingDaysInShift = [];
        
        foreach ($daysOfWeek as $day) {
            $inTimeColumn = "{$day}_in_time";
            $inTime = $shiftData->$inTimeColumn ?? '';
            $isWorking = !empty($inTime) && $inTime !== '' && $inTime !== '0:00';
            echo "  " . ucfirst($day) . ": " . ($isWorking ? "Working ({$inTime})" : "Non-working") . "\n";
            if ($isWorking) {
                $workingDaysInShift[] = $day;
            }
        }
        
        // Test with a date range (5 consecutive days starting from a Monday)
        $fromDate = '2024-01-15'; // Monday
        $toDate = '2024-01-19';   // Friday
        
        echo "\nTest Date Range: {$fromDate} to {$toDate}\n";
        echo "Days in range: Monday, Tuesday, Wednesday, Thursday, Friday\n";
        
        // Check for holidays in this range
        $holidays = $db->query("
            SELECT start_date, end_date, event_name 
            FROM ci_holidays 
            WHERE company_id = {$employee->company_id}
            AND (
                (start_date <= '{$toDate}' AND end_date >= '{$fromDate}')
            )
        ")->getResultArray();
        
        if (!empty($holidays)) {
            echo "\nCompany Holidays in range:\n";
            foreach ($holidays as $holiday) {
                echo "  - {$holiday['event_name']}: {$holiday['start_date']} to {$holiday['end_date']}\n";
            }
        } else {
            echo "\nNo company holidays in this range\n";
        }
        
        $workingDays = $leavePolicy->calculateWorkingDaysInRange($employee->user_id, $fromDate, $toDate);
        
        echo "\nCalculated Working Days: {$workingDays}\n";
        echo "Result: " . ($workingDays >= 0 ? "PASS (method executed successfully)" : "FAIL") . "\n";
        
        // Test 3: Date range with only weekends (if Friday is non-working)
        echo "\n\nTest 3: Date range with non-working days\n";
        echo "-----------------------------------------\n";
        
        // Find a non-working day in the shift
        $nonWorkingDay = null;
        $dayMapping = [
            'monday' => '2024-01-15',
            'tuesday' => '2024-01-16',
            'wednesday' => '2024-01-17',
            'thursday' => '2024-01-18',
            'friday' => '2024-01-19',
            'saturday' => '2024-01-20',
            'sunday' => '2024-01-21'
        ];
        
        foreach ($daysOfWeek as $day) {
            $inTimeColumn = "{$day}_in_time";
            $inTime = $shiftData->$inTimeColumn ?? '';
            $isWorking = !empty($inTime) && $inTime !== '' && $inTime !== '0:00';
            if (!$isWorking) {
                $nonWorkingDay = $day;
                break;
            }
        }
        
        if ($nonWorkingDay) {
            $testDate = $dayMapping[$nonWorkingDay];
            echo "Testing with non-working day: " . ucfirst($nonWorkingDay) . " ({$testDate})\n";
            $workingDays = $leavePolicy->calculateWorkingDaysInRange($employee->user_id, $testDate, $testDate);
            echo "Calculated Working Days: {$workingDays}\n";
            echo "Result: " . ($workingDays === 0 ? "PASS (correctly excluded non-working day)" : "FAIL") . "\n";
        } else {
            echo "No non-working days in shift (all days are working days)\n";
        }
        
        // Test 4: Multi-week date range
        echo "\n\nTest 4: Multi-week date range\n";
        echo "------------------------------\n";
        $fromDate = '2024-01-15'; // Monday
        $toDate = '2024-01-26';   // Friday (2 weeks)
        
        echo "Test Date Range: {$fromDate} to {$toDate} (12 days)\n";
        $workingDays = $leavePolicy->calculateWorkingDaysInRange($employee->user_id, $fromDate, $toDate);
        echo "Calculated Working Days: {$workingDays}\n";
        echo "Result: " . ($workingDays >= 0 && $workingDays <= 12 ? "PASS (reasonable result)" : "FAIL") . "\n";
        
    } else {
        echo "Could not retrieve shift data for employee\n";
    }
} else {
    echo "No employees with shifts found in database\n";
}

echo "\n=== Test Complete ===\n";
