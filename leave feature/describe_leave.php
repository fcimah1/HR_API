<?php
$mysqli = new mysqli("localhost", "root", "", "sfessa_hr");
if ($mysqli->connect_errno) { die("Conn failed: " . $mysqli->connect_error); }
$res = $mysqli->query("DESCRIBE ci_leave_applications");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
