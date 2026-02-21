<?php
$mysqli = new mysqli("localhost", "root", "", "sfessa_hr");

if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
    exit();
}

$sql = "SELECT * FROM ci_leave_policy_countries WHERE leave_type = 'maternity'";
$result = $mysqli->query($sql);

echo "\n=== MATERNITY POLICIES ===\n";
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "NO POLICIES FOUND\n";
}
echo "==========================\n";
