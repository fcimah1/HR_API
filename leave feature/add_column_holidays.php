<?php
$mysqli = new mysqli("localhost", "root", "", "sfessa_hr");
if ($mysqli->connect_errno) { die("Conn failed: " . $mysqli->connect_error); }

// Check if column exists
$res = $mysqli->query("SHOW COLUMNS FROM ci_leave_applications LIKE 'include_holidays'");
if ($res->num_rows == 0) {
    echo "Adding include_holidays column...\n";
    $sql = "ALTER TABLE ci_leave_applications ADD COLUMN include_holidays TINYINT(1) NOT NULL DEFAULT 0 AFTER is_deducted";
    if ($mysqli->query($sql)) {
        echo "Column added successfully.\n";
    } else {
        echo "Error adding column: " . $mysqli->error . "\n";
    }
} else {
    echo "Column include_holidays already exists.\n";
}
?>
