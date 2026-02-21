<?php
// Load CodeIgniter framework (minimal) - actually just use pure PDO for simple check to avoid bootstrap issues
// Or better, let's use the framework properly if possible, but a standalone script is safer.

$hostname = "localhost";
$username = "root";
$password = ""; // Assuming default WAMP
$database = "sfessa_hr"; // Guessing based on previous context, but will check env or assume user knows. 
// Wait, I should use the CI4 bootstrap.

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bootstrap CI4
$min_bootstrap = true;
require 'index.php'; 

use Config\Database;

$db = Database::connect();
$query = $db->query("SELECT * FROM ci_leave_policy_countries WHERE leave_type = 'maternity'");
$results = $query->getResultArray();

echo "\n=== MATERNITY POLICIES ===\n";
print_r($results);
echo "==========================\n";
