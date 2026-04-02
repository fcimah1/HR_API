<?php
$db = new mysqli('localhost', 'root', '', 'sfessa_hr');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$result = $db->query("SHOW TABLES LIKE '%policy%'");
while($row = $result->fetch_row()) {
    print_r($row);
}
$db->close();
?>
