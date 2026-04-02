<?php
$mysqli = new mysqli("localhost", "root", "", "sfessa_hr");
if ($mysqli->connect_errno) { die("Conn failed: " . $mysqli->connect_error); }

echo "=== CHECKING FOR HAJJ LEAVE MAPPING ===\n";
$res = $mysqli->query("SELECT * FROM ci_leave_policy_mapping WHERE system_leave_type LIKE '%hajj%' OR system_leave_type LIKE '%pilgrimage%'");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n=== CHECKING CONSTANTS OR TYPES ===\n";
$res = $mysqli->query("SELECT * FROM ci_leave_type WHERE type_name LIKE '%hajj%'");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
