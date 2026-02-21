<?php

define('ROOTPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
require_once ROOTPATH . 'vendor/autoload.php';

$pathsConfig = ROOTPATH . 'app/Config/Paths.php';
require realpath($pathsConfig) ?: $pathsConfig;

$paths = new Config\Paths();
$bootstrap = rtrim($paths->systemDirectory, '\\/ ') . '/bootstrap.php';
$app = require realpath($bootstrap) ?: $bootstrap;

$db = \Config\Database::connect();

echo "Finding valid employees with shift and salary...\n\n";

$users = $db->table('ci_erp_users_details')
    ->where('office_shift_id IS NOT NULL')
    ->where('basic_salary >', 0)
    ->limit(10)
    ->get()
    ->getResultArray();

if (empty($users)) {
    echo "No employees found with shift and salary!\n";
    exit(1);
}

echo "Found " . count($users) . " employees:\n\n";

foreach ($users as $user) {
    echo "User ID: {$user['user_id']}\n";
    echo "- Shift ID: {$user['office_shift_id']}\n";
    echo "- Basic Salary: {$user['basic_salary']}\n\n";
}

echo "Use any of these employee IDs for testing.\n";
