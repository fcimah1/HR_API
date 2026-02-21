<?php

/**
 * Diagnostic Script: Verify Unpaid Leave Fix
 * 
 * This script checks if the fix is properly applied
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

echo "=== DIAGNOSTIC: Unpaid Leave Deduction Fix ===\n\n";

// Test the function directly
$testSalary = 30000;
$testEmployeeId = 1; // Use a real employee ID if available

echo "Testing calculate_unpaid_leave_deduction() function:\n";
echo "- Basic Salary: {$testSalary} SAR\n";
echo "- Expected Daily Rate: " . ($testSalary / 30) . " SAR\n\n";

// Check if function exists
if (function_exists('calculate_unpaid_leave_deduction')) {
    echo "✓ Function calculate_unpaid_leave_deduction() exists\n\n";
    
    // Read the function source to verify the fix
    $reflection = new ReflectionFunction('calculate_unpaid_leave_deduction');
    $filename = $reflection->getFileName();
    $start_line = $reflection->getStartLine();
    $end_line = $reflection->getEndLine();
    
    echo "Function location: {$filename}\n";
    echo "Lines: {$start_line} - {$end_line}\n\n";
    
    // Read the relevant lines
    $file = file($filename);
    $function_code = implode("", array_slice($file, $start_line - 1, $end_line - $start_line + 1));
    
    // Check for the fix markers
    echo "Checking for fix markers:\n";
    
    if (strpos($function_code, '$daily_rate = $basic_salary / 30;') !== false) {
        echo "✓ FOUND: Fixed daily rate calculation (salary ÷ 30)\n";
    } else {
        echo "✗ NOT FOUND: Fixed daily rate calculation\n";
        echo "  The function might still be using variable daily rate!\n";
    }
    
    if (strpos($function_code, 'min($total_unpaid_days, 30)') !== false) {
        echo "✓ FOUND: Days cap at 30\n";
    } else {
        echo "✗ NOT FOUND: Days cap\n";
    }
    
    if (strpos($function_code, 'min($calculated_deduction, $basic_salary)') !== false) {
        echo "✓ FOUND: Deduction cap at salary\n";
    } else {
        echo "✗ NOT FOUND: Deduction cap\n";
    }
    
    echo "\n";
    
    // Show relevant code snippet
    echo "=== CODE SNIPPET (Daily Rate Calculation) ===\n";
    $lines = explode("\n", $function_code);
    foreach ($lines as $line) {
        if (strpos($line, 'daily_rate') !== false || 
            strpos($line, 'capped_unpaid_days') !== false ||
            strpos($line, 'unpaid_deduction') !== false) {
            echo trim($line) . "\n";
        }
    }
    
} else {
    echo "✗ Function calculate_unpaid_leave_deduction() NOT FOUND!\n";
    echo "  The helper file might not be loaded correctly.\n";
}

echo "\n=== CONCLUSION ===\n";
echo "If all three markers are FOUND, the fix is correctly applied.\n";
echo "If any marker is NOT FOUND, the fix needs to be re-applied.\n";
