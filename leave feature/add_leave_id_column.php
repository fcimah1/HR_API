<?php
// Script to add leave_id column to ci_payslip_statutory_deductions
$db = new mysqli('localhost', 'root', '', 'sfessa_hr');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Add leave_id column
$sql = "SHOW COLUMNS FROM `ci_payslip_statutory_deductions` LIKE 'leave_id'";
$result = $db->query($sql);

if ($result->num_rows == 0) {
    $sql = "ALTER TABLE `ci_payslip_statutory_deductions` ADD COLUMN `leave_id` INT DEFAULT NULL AFTER `staff_id`";
    if ($db->query($sql) === TRUE) {
        echo "Column leave_id added successfully.\n";
    } else {
        echo "Error adding column: " . $db->error . "\n";
    }
} else {
    echo "Column leave_id already exists.\n";
}

$db->close();
?>
