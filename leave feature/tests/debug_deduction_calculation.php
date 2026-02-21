<?php

/**
 * Debug script to understand the deduction calculation discrepancy
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../app/Config/Paths.php';
$paths = new Config\Paths();
$bootstrap = rtrim($paths->systemDirectory, '\\/ ') . '/bootstrap.php';
$app = require realpath($bootstrap) ?: $bootstrap;

use App\Libraries\LeavePolicy;

$leavePolicy = new LeavePolicy();
$db = \Config\Database::connect();

echo "=== Debugging Deduction Calculation ===\n\n";

// Test scenario from the failing test
$fromDate = '2026-04-01'; // Example date
$toDate = '2026-04-30';
$cumulativeBefore = 50; // Example: 50 days already used
$basicSalary = 10000;
$countryCode = 'SA';

echo "Scenario:\n";
echo "  From: {$fromDate}\n";
echo "  To: {$toDate}\n";
echo "  Cumulative Before: {$cumulativeBefore} days\n";
echo "  Basic Salary: {$basicSalary} SAR\n";
echo "  Country: {$countryCode}\n\n";

// Calculate days in this period
$daysInPeriod = (strtotime($toDate) - strtotime($fromDate)) / (60 * 60 * 24) + 1;
echo "Days in period: {$daysInPeriod}\n\n";

// Get tier split
$tierSegments = $leavePolicy->calculateTierSplit($cumulativeBefore, $daysInPeriod, $countryCode, 'sick');

echo "Tier Segments:\n";
$dailyRate = $basicSalary / 30;
$totalDeduction = 0;

foreach ($tierSegments as $segment) {
    echo "  Tier {$segment['tier_order']}: {$segment['days']} days at {$segment['payment_percentage']}% pay\n";
    
    if ($segment['payment_percentage'] < 100) {
        $deductionPercent = 100 - $segment['payment_percentage'];
        $deductionAmount = $segment['days'] * $dailyRate * ($deductionPercent / 100);
        echo "    Deduction: {$segment['days']} × {$dailyRate} × {$deductionPercent}% = {$deductionAmount} SAR\n";
        $totalDeduction += $deductionAmount;
    } else {
        echo "    No deduction (100% pay)\n";
    }
}

echo "\nTotal Deduction: " . round($totalDeduction, 2) . " SAR\n";

echo "\n=== Saudi Arabia Sick Leave Tiers ===\n";
echo "Days 1-30: 100% pay (0% deduction)\n";
echo "Days 31-90: 75% pay (25% deduction)\n";
echo "Days 91-120: 0% pay (100% deduction)\n";
echo "Days 121+: 0% pay (100% deduction)\n\n";

echo "With cumulative {$cumulativeBefore} days already used:\n";
echo "  Days 1-30 tier: " . max(0, 30 - $cumulativeBefore) . " days remaining\n";
echo "  Days 31-90 tier: " . max(0, min(60, 90 - $cumulativeBefore)) . " days remaining\n";
echo "  Days 91-120 tier: " . max(0, min(30, 120 - $cumulativeBefore)) . " days remaining\n";
