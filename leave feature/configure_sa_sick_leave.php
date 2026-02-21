<?php
// Script to configure Saudi Arabia Sick Leave Tiers
$db = new mysqli('localhost', 'root', '', 'sfessa_hr');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$countryCode = 'SA';
$leaveType = 'sick';

// 1. Clear existing tiers for SA Sick Leave
$sql = "DELETE FROM `ci_leave_policy_countries` WHERE `country_code` = '$countryCode' AND `leave_type` = '$leaveType'";
if ($db->query($sql) === TRUE) {
    echo "Existing tiers deleted.\n";
} else {
    echo "Error deleting tiers: " . $db->error . "\n";
}

// 2. Insert new tiers
$tiers = [
    [1, 30, 100, 0, NULL], // Tier 1: 30 days, 100% pay
    [2, 60, 75, 0, NULL],  // Tier 2: 60 days, 75% pay
    [3, 30, 0, 0, NULL]    // Tier 3: 30 days, 0% pay
];

$stmt = $db->prepare("INSERT INTO `ci_leave_policy_countries` (`country_code`, `leave_type`, `tier_order`, `entitlement_days`, `payment_percentage`, `service_years_min`, `service_years_max`, `company_id`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())");

foreach ($tiers as $tier) {
    $stmt->bind_param("ssiiiii", $countryCode, $leaveType, $tier[0], $tier[1], $tier[2], $tier[3], $tier[4]);
    if ($stmt->execute()) {
        echo "Inserted Tier {$tier[0]}: {$tier[1]} days @ {$tier[2]}% pay.\n";
    } else {
        echo "Error inserting tier {$tier[0]}: " . $stmt->error . "\n";
    }
}

$stmt->close();
$db->close();
?>
