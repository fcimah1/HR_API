<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$employees = App\Models\User::where('company_id', 24)
    ->where('is_active', true)
    ->select('user_id', 'first_name', 'last_name', 'company_id')
    ->get();

echo "الموظفين النشطين في الشركة 24:\n";
echo "================================\n";
foreach ($employees as $emp) {
    echo "ID: {$emp->user_id} | {$emp->first_name} {$emp->last_name} | Company: {$emp->company_id}\n";
}
