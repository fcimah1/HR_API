<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$leaveId = $argv[1] ?? 79;
$leave = App\Models\LeaveApplication::find($leaveId);

if ($leave) {
    echo "Leave ID: {$leave->leave_id}\n";
    echo "Employee ID: {$leave->employee_id}\n";
    echo "Status: " . ($leave->status === true ? 'approved' : ($leave->status === false ? 'pending' : 'rejected')) . "\n";
    echo "Status value: " . var_export($leave->status, true) . "\n";
} else {
    echo "Leave not found\n";
}
