<?php
// Verify SA Sick Leave Math
// Simulates 120 days request: 30 days @ 100%, 60 days @ 75%, 30 days @ 0%

$dailyRate = 1000; // Assume 1000 SAR daily rate
$totalDays = 120;
$cumulativeBefore = 0;

$tiers = [
    ['order' => 1, 'days' => 30, 'pay' => 100],
    ['order' => 2, 'days' => 60, 'pay' => 75],
    ['order' => 3, 'days' => 30, 'pay' => 0]
];

$remainingDays = $totalDays;
$currentPosition = $cumulativeBefore;
$deductions = [];

echo "Simulating 120 Days Request (SA Policy)...\n";
echo "Daily Rate: $dailyRate\n";
echo "--------------------------------------------------\n";

$runningTotal = 0;
foreach ($tiers as $tier) {
    // Determine tier boundaries
    $tierStart = $runningTotal;
    $tierEnd = $runningTotal + $tier['days'];
    $runningTotal += $tier['days'];
    
    // Check overlap with current request
    // Overlap logic: max(start, tierStart) to min(end, tierEnd)
    // Request range: currentPosition to currentPosition + totalDays
    
    // Easier logic: simply fill buckets
    if ($remainingDays <= 0) break;
    
    $daysInTier = min($remainingDays, $tier['days']);
    
    if ($daysInTier > 0) {
        $deductionPercent = 100 - $tier['pay'];
        $amount = $daysInTier * $dailyRate * ($deductionPercent / 100);
        
        $deductions[] = [
            'tier' => $tier['order'],
            'days' => $daysInTier,
            'pay_percent' => $tier['pay'],
            'deduction_percent' => $deductionPercent,
            'deduction_amount' => $amount
        ];
        
        $remainingDays -= $daysInTier;
    }
}

// Display Results
foreach ($deductions as $d) {
    echo "Tier {$d['tier']}: {$d['days']} days (Pay: {$d['pay_percent']}%, Ded: {$d['deduction_percent']}%)\n";
    echo "  -> Deduction Amount: {$d['deduction_amount']}\n";
}

echo "--------------------------------------------------\n";
echo "Remaining Days (overflow): $remainingDays\n";

// Validation
// Expect:
// Tier 1: 30 days, Ded 0
// Tier 2: 60 days, Ded 25% * 1000 * 60 = 15000
// Tier 3: 30 days, Ded 100% * 1000 * 30 = 30000
// Total Deduction: 45000

$totalDeduction = array_sum(array_column($deductions, 'deduction_amount'));
echo "Total Deduction: $totalDeduction\n";

if ($totalDeduction == 45000) {
    echo "SUCCESS: Logic matches SA requirement.\n";
} else {
    echo "FAILURE: Logic mismatch.\n";
}
?>
