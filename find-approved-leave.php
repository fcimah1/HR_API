<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$leave = App\Models\LeaveApplication::where('employee_id', 36)
    ->where('status', true)
    ->first();

if ($leave) {
    echo "Approved Leave ID: {$leave->leave_id}\n";
} else {
    echo "No approved leaves found for employee 36\n";
}
