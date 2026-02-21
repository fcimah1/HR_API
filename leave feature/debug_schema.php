<?php
$mysqli = new mysqli("localhost", "root", "", "sfessa_hr");
if ($mysqli->connect_errno) { die("Conn failed: " . $mysqli->connect_error); }

echo "=== TABLES ===\n";
$tables = [];
$res = $mysqli->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    $tables[] = $row[0];
    echo $row[0] . "\n";
}

echo "\n=== COLUMNS SEARCH (basic_salary) ===\n";
foreach ($tables as $t) {
    $res = $mysqli->query("SHOW COLUMNS FROM `$t` LIKE '%basic_salary%'");
    if ($res->num_rows > 0) {
        echo "FOUND 'basic_salary' in TABLE: $t\n";
        while ($r = $res->fetch_assoc()) {
            print_r($r);
        }
    }
}
?>
