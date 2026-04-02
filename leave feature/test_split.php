<?php
// Standalone script to test calculateTierSplit logic locally
// Simulating the environment

$hostname = "localhost";
$username = "root";
$password = "";
$database = "sfessa_hr";
$mysqli = new mysqli($hostname, $username, $password, $database);

function getAllPolicyTiers($countryCode, $systemLeaveType, $mysqli) {
    if ($mysqli->connect_errno) return [];
    
    $sql = "SELECT * FROM ci_leave_policy_countries WHERE country_code = '$countryCode' AND leave_type = '$systemLeaveType' AND company_id = 0 AND is_active = 1 ORDER BY tier_order ASC";
    $result = $mysqli->query($sql);
    
    $rows = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function calculateTierSplit($cumulativeDays, $requestedDays, $countryCode, $systemLeaveType, $mysqli)
{
    $tiers = getAllPolicyTiers($countryCode, $systemLeaveType, $mysqli);
    
    if (empty($tiers)) {
        echo "No tiers found for $countryCode / $systemLeaveType\n";
        return []; 
    }
    
    echo "Found " . count($tiers) . " tiers.\n";
    
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
            'end' => $runningTotal
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
                'deduct_pct' => 100 - $boundary['payment_percentage']
            ];
            
            $currentPosition += $daysInThisTier;
            $remainingDays -= $daysInThisTier;
        }
    }
    
    if ($remainingDays > 0) {
        echo "WARNING: $remainingDays days left that exceeded all tiers.\n";
        // Handle overflow (usually means 100% deduction or invalid)
        $segments[] = [
            'tier' => 'OVERFLOW',
            'days' => $remainingDays,
            'pay_pct' => 0,
            'deduct_pct' => 100
        ];
    }
    
    return $segments;
}

echo "Testing Split for SA (Saudi Arabia) - Maternity - 75 Days (Cumulative 0)\n";
$segments = calculateTierSplit(0, 75, 'SA', 'maternity', $mysqli);
print_r($segments);

echo "\nTesting Split for SA (Saudi Arabia) - Maternity - 75 Days (Cumulative 65)\n";
// 65 used. 5 left in Tier 1. 70 needed. So 5 in Tier 1, 70 in Tier 2.
$segments = calculateTierSplit(65, 75, 'SA', 'maternity', $mysqli);
print_r($segments);

?>
