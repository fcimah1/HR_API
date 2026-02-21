<?php
$mysqli = new mysqli("localhost", "root", "", "sfessa_hr");
if ($mysqli->connect_errno) { die("Conn failed: " . $mysqli->connect_error); }

echo "=== LEAVE APPLICATIONS (Recent) ===\n";
$res = $mysqli->query("SELECT leave_id, employee_id, leave_type_id, leave_year, from_date, to_date, leave_hours, status FROM ci_leave_applications ORDER BY leave_id DESC LIMIT 10");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n=== MAPPING for MATERNITY ===\n";
$res = $mysqli->query("SELECT * FROM ci_leave_policy_mapping WHERE system_leave_type = 'maternity'");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
