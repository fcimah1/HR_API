<?php
$mysqli = new mysqli("localhost", "root", "", "sfessa_hr");
if ($mysqli->connect_errno) { die("Conn failed: " . $mysqli->connect_error); }

echo "=== MAPPING CHECK ===\n";
$res = $mysqli->query("SELECT * FROM ci_leave_policy_mapping WHERE system_leave_type = 'maternity'");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n=== MATERNITY DEDUCTIONS IN DB ===\n";
$res = $mysqli->query("SELECT * FROM ci_payslip_statutory_deductions WHERE pay_title LIKE '%Maternity%'");
if ($res->num_rows == 0) {
    echo "No deductions found!\n";
}
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
