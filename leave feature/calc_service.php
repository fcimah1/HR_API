<?php
$mysqli = new mysqli("localhost", "root", "", "sfessa_hr");
if ($mysqli->connect_errno) { die("Conn failed: " . $mysqli->connect_error); }

echo "=== USER 742 DETAILS ===\n";
$res = $mysqli->query("SELECT user_id, date_of_joining FROM ci_erp_users_details WHERE user_id = 742");
while ($row = $res->fetch_assoc()) {
    print_r($row);
    
    // Simulate calculation
    $joinDate = new DateTime($row['date_of_joining']);
    $now = new DateTime();
    $diff = $now->diff($joinDate);
    $serviceYears = $diff->y + ($diff->m / 12);
    
    echo "Current Date: " . $now->format('Y-m-d') . "\n";
    echo "Service Years: " . number_format($serviceYears, 2) . "\n";
}
?>
