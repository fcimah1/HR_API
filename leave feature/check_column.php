<?php
$db = new mysqli('localhost', 'root', '', 'sfessa_hr');
if ($db->connect_error) { die("Connection failed: " . $db->connect_error); }

$table = 'ci_leave_applications';
$column = 'salary_deduction_applied';

$result = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");

if ($result->num_rows > 0) {
    echo "EXISTS";
} else {
    echo "MISSING";
    // Add it if missing (for my verification, though user asked for SQL)
    $sql = "ALTER TABLE `$table` ADD COLUMN `$column` TINYINT(1) DEFAULT 0 AFTER `status`";
    if ($db->query($sql) === TRUE) {
        echo " - ADDED AUTOMATICALLY";
    } else {
        echo " - ERROR ADDING: " . $db->error;
    }
}
$db->close();
?>
