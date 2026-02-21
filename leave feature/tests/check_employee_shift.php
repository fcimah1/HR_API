<?php

define('ROOTPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
require_once ROOTPATH . 'vendor/autoload.php';

$pathsConfig = ROOTPATH . 'app/Config/Paths.php';
require realpath($pathsConfig) ?: $pathsConfig;

$paths = new Config\Paths();
$bootstrap = rtrim($paths->systemDirectory, '\\/ ') . '/bootstrap.php';
$app = require realpath($bootstrap) ?: $bootstrap;

$db = \Config\Database::connect();

$employeeId = 1;

echo "Checking employee {$employeeId} shift configuration...\n\n";

$user = $db->table('ci_erp_users_details')->where('user_id', $employeeId)->get()->getRowArray();

if (!$user) {
    echo "Employee not found!\n";
    exit(1);
}

echo "Employee Details:\n";
echo "- User ID: {$user['user_id']}\n";
echo "- Office Shift ID: " . ($user['office_shift_id'] ?? 'NULL') . "\n";
echo "- Basic Salary: " . ($user['basic_salary'] ?? 'NULL') . "\n\n";

if (!$user['office_shift_id']) {
    echo "ERROR: No shift assigned to employee!\n";
    echo "This is why createUnpaidLeaveDeductions() returned false.\n";
    exit(1);
}

$shift = $db->table('ci_office_shifts')->where('office_shift_id', $user['office_shift_id'])->get()->getRowArray();

if (!$shift) {
    echo "ERROR: Shift not found!\n";
    exit(1);
}

echo "Shift Details:\n";
echo "- Shift ID: {$shift['office_shift_id']}\n";
echo "- Monday: " . ($shift['monday_in_time'] ? 'Yes' : 'No') . "\n";
echo "- Tuesday: " . ($shift['tuesday_in_time'] ? 'Yes' : 'No') . "\n";
echo "- Wednesday: " . ($shift['wednesday_in_time'] ? 'Yes' : 'No') . "\n";
echo "- Thursday: " . ($shift['thursday_in_time'] ? 'Yes' : 'No') . "\n";
echo "- Friday: " . ($shift['friday_in_time'] ? 'Yes' : 'No') . "\n";
echo "- Saturday: " . ($shift['saturday_in_time'] ? 'Yes' : 'No') . "\n";
echo "- Sunday: " . ($shift['sunday_in_time'] ? 'Yes' : 'No') . "\n";

echo "\n✓ Employee has valid shift configuration\n";
