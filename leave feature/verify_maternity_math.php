<?php
// verify_maternity_math.php
// This script simulates the backend logic for Maternity Leave Calculation
// counting distinct tiers and handling overflow.

$hostname = "localhost";
$username = "root";
$password = "";
$database = "sfessa_hr";
$mysqli = new mysqli($hostname, $username, $password, $database);

if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

function getMaternityTiers($countryCode, $mysqli) {
    $sql = "SELECT * FROM ci_leave_policy_countries 
            WHERE country_code = '$countryCode' 
            AND leave_type = 'maternity' 
            AND company_id = 0 
            AND is_active = 1 
            ORDER BY tier_order ASC";
    $result = $mysqli->query($sql);
    $rows = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function simulateCalculation($cumulativeDays, $requestedDays, $countryCode, $mysqli) {
    echo "\n--------------------------------------------------\n";
    echo "TEST SCENARIO: Country=$countryCode, Used=$cumulativeDays, Requested=$requestedDays\n";
    
    $tiers = getMaternityTiers($countryCode, $mysqli);
    
    if (empty($tiers)) {
        echo "No tiers found! Check database.\n";
        return;
    }
    
    $segments = [];
    $remainingDays = $requestedDays;
    $currentPosition = $cumulativeDays;
    
    // Calculate tier boundaries (cumulative)
    $tierBoundaries = [];
    $runningTotal = 0;
    foreach ($tiers as $tier) {
        $runningTotal += $tier['entitlement_days'];
        $tierBoundaries[] = [
            'tier_order' => $tier['tier_order'],
            'payment_percentage' => $tier['payment_percentage'],
            'start' => $runningTotal - $tier['entitlement_days'],
            'end' => $runningTotal,
            'desc' => $tier['policy_description_en']
        ];
    }
    
    // Distribute requested days across tiers
    foreach ($tierBoundaries as $boundary) {
        if ($remainingDays <= 0) break;
        
        // Skip tiers that are already fully consumed
        if ($currentPosition >= $boundary['end']) {
            continue;
        }
        
        // Calculate days that fall in this tier
        $tierStart = max($currentPosition, $boundary['start']);
        $tierEnd = min($currentPosition + $remainingDays, $boundary['end']);
        $daysInThisTier = $tierEnd - $tierStart;
        
        if ($daysInThisTier > 0) {
            $segments[] = [
                'tier' => $boundary['tier_order'],
                'days' => $daysInThisTier,
                'pay_pct' => $boundary['payment_percentage'],
                'deduct_pct' => 100 - $boundary['payment_percentage'],
                'info' => "Matched Tier {$boundary['tier_order']} ({$boundary['desc']})"
            ];
            
            $currentPosition += $daysInThisTier;
            $remainingDays -= $daysInThisTier;
        }
    }
    
    // HANDLE OVERFLOW (The Fix)
    if ($remainingDays > 0) {
        $segments[] = [
            'tier' => 'OVERFLOW',
            'days' => $remainingDays,
            'pay_pct' => 0,
            'deduct_pct' => 100,
            'info' => "Exceeded all policies (Unpaid)"
        ];
    }
    
    // Output Result
    foreach ($segments as $s) {
        echo "  -> Segment: {$s['days']} Days | Pay: {$s['pay_pct']}% | Deduct: {$s['deduct_pct']}% | {$s['info']}\n";
    }
    
    // Summary
    $totalDeductedDays = 0;
    foreach($segments as $s) {
        if ($s['deduct_pct'] > 0) $totalDeductedDays += $s['days'];
    }
    echo "  => TOTAL DEDUCTIBLE DAYS: $totalDeductedDays\n";
}

// 1. Normal Case: Within first tier (70 days paid)
simulateCalculation(0, 50, 'SA', $mysqli);

// 2. Split Case: Crosses from Tier 1 (70 paid) to Tier 2 (Unlimited/60 unpaid?)
simulateCalculation(0, 80, 'SA', $mysqli); 
// Expect: 70 Paid, 10 Unpaid

// 3. Overflow Case: Exceeds total defined tiers (70 + 60 = 130). Requesting 140.
simulateCalculation(0, 140, 'SA', $mysqli);
// Expect: 70 Paid, 60 Unpaid (Tier 2), 10 Unpaid (Overflow)

?>
