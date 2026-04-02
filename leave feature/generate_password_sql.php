<?php
/**
 * Password SQL Generator Script
 * 
 * This script reads JSON files with employee passwords and generates
 * SQL UPDATE statements with hashed passwords.
 * 
 * USAGE:
 * 1. Run: php generate_password_sql.php
 * 2. Review the generated SQL file
 * 3. Execute the SQL file on your remote database
 * 4. DELETE this script and the SQL file after use
 * 
 * OUTPUT: password_updates.sql (ready to execute on your database)
 */

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================
$config = [
    'table_name' => 'ci_erp_users',      // Your database table name
    'username_column' => 'username',      // Username column name
    'email_column' => 'email',            // Email column name
    'password_column' => 'password',      // Password column name
];

// ============================================================================
// FILES CONFIGURATION
// ============================================================================
$files = [
    [
        'file' => 'data entry/راوي.json',
        'password_field' => 'اليوزر',
        'username_field' => 'أسم المستخدم',
        'email_field' => 'البريد الإلكتروني'
    ],
    [
        'file' => 'data entry/فرع الاحساء.json',
        'password_field' => 'الباسورد ',
        'username_field' => 'اسم المستخدم ',
        'email_field' => 'البريد الإلكتروني'
    ],
    [
        'file' => 'data entry/مصنع روض الاصيل.json',
        'password_field' => 'الرقم السري ',
        'username_field' => 'أسم المستخدم ',
        'email_field' => 'البريد الإلكتروني'
    ],
    [
        'file' => 'data entry/موظفي شركة أزهار الفخامة.json',
        'password_field' => 'اليوزر',
        'username_field' => 'أسم المستخدم',
        'email_field' => 'البريد الإلكتروني'
    ],
    [
        'file' => 'data entry/موظفي شركة الأختيار الأول .json',
        'password_field' => 'الباسورد',
        'username_field' => 'اسم المستخدم ',
        'email_field' => 'البريد الإلكتروني'
    ],
    [
        'file' => 'data entry/موظفي شركة عالم المراكز.json',
        'password_field' => 'الباسورد',
        'username_field' => 'أسم المستخذدم ',
        'email_field' => 'البريد الإلكتروني'
    ],
    [
        'file' => 'data entry/الإدارة العامة - مصر.json',
        'password_field' => 'اليوزر',
        'username_field' => 'اسم المستخدم',
        'email_field' => 'البريد الإلكتروني'
    ],
    [
        'file' => 'data entry/الإدارة العامة جدة.json',
        'password_field' => 'الباسورد ',
        'username_field' => 'اسم المستخدم ',
        'email_field' => 'البريد الإلكتروني'
    ],
    [
        'file' => 'data entry/فرع  الرياض.json',
        'password_field' => 'اليوزر',
        'username_field' => 'أسم المستخدم ',
        'email_field' => 'البريد الإلكتروني'
    ],
    [
        'file' => 'data entry/الاداره المركزيه لاسواق ميم.json',
        'password_field' => 'الباسورد ',
        'username_field' => 'اسم المستخدم ',
        'email_field' => 'البريد الإلكتروني'
    ],
];

// Password hashing options (matching your system)
$hash_options = ['cost' => 12];

// Output SQL file
$outputFile = 'password_updates.sql';

// Statistics
$stats = [
    'total_processed' => 0,
    'sql_generated' => 0,
    'skipped' => 0,
    'errors' => []
];

// ============================================================================
// START PROCESSING
// ============================================================================

echo "=================================================\n";
echo "Password SQL Generator Started\n";
echo "=================================================\n\n";

// Open output file
$sqlFile = fopen($outputFile, 'w');
if (!$sqlFile) {
    die("❌ ERROR: Cannot create output file: {$outputFile}\n");
}

// Write SQL file header
fwrite($sqlFile, "-- ============================================================================\n");
fwrite($sqlFile, "-- Password Update SQL Script\n");
fwrite($sqlFile, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
fwrite($sqlFile, "-- Table: {$config['table_name']}\n");
fwrite($sqlFile, "-- ============================================================================\n");
fwrite($sqlFile, "-- IMPORTANT: Review this file before executing!\n");
fwrite($sqlFile, "-- This script updates BOTH username AND password with BCRYPT hashed values\n");
fwrite($sqlFile, "-- Records are matched by EMAIL for safety (email is unique)\n");
fwrite($sqlFile, "-- ============================================================================\n\n");
fwrite($sqlFile, "SET SQL_SAFE_UPDATES = 0;\n\n");
fwrite($sqlFile, "START TRANSACTION;\n\n");

// Process each file
foreach ($files as $index => $fileConfig) {
    $filePath = $fileConfig['file'];
    $passwordField = $fileConfig['password_field'];
    $usernameField = $fileConfig['username_field'];
    $emailField = $fileConfig['email_field'];
    
    echo "Processing file " . ($index + 1) . ": " . basename($filePath) . "\n";
    echo "Password field: {$passwordField}\n";
    echo "Username field: {$usernameField}\n";
    echo "Email field: {$emailField}\n";
    echo "-------------------------------------------------\n";
    
    // Check if file exists
    if (!file_exists($filePath)) {
        echo "❌ ERROR: File not found: {$filePath}\n\n";
        $stats['errors'][] = "File not found: {$filePath}";
        continue;
    }
    
    // Check file size
    $fileSize = filesize($filePath);
    echo "File size: " . number_format($fileSize) . " bytes\n";
    
    // Read JSON file
    $jsonContent = file_get_contents($filePath);
    
    // Remove BOM if present (common issue with UTF-8 files)
    $jsonContent = preg_replace('/^\xEF\xBB\xBF/', '', $jsonContent);
    
    // Trim whitespace
    $jsonContent = trim($jsonContent);
    
    // Check if it's wrapped in array brackets
    if (substr($jsonContent, 0, 1) !== '[') {
        echo "   ⚠️  JSON not wrapped in array brackets, fixing...\n";
        // Wrap in array brackets
        $jsonContent = '[' . $jsonContent . ']';
    }
    
    $employees = json_decode($jsonContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ ERROR: Invalid JSON in file: " . json_last_error_msg() . "\n\n";
        $stats['errors'][] = "Invalid JSON in {$filePath}: " . json_last_error_msg();
        continue;
    }
    
    // Ensure $employees is an array
    if (!is_array($employees)) {
        echo "❌ ERROR: JSON content is not an array\n\n";
        $stats['errors'][] = "Invalid format in {$filePath}: Not an array";
        continue;
    }
    
    echo "✅ Parsed " . count($employees) . " employees\n";
    
    // Write file section header in SQL
    fwrite($sqlFile, "-- ============================================================================\n");
    fwrite($sqlFile, "-- File: " . basename($filePath) . "\n");
    fwrite($sqlFile, "-- ============================================================================\n\n");
    
    // Process each employee
    $fileProcessed = 0;
    $fileGenerated = 0;
    
    foreach ($employees as $employee) {
        $stats['total_processed']++;
        $fileProcessed++;
        
        // Get username, email, and password from employee data
        $username = isset($employee[$usernameField]) ? trim($employee[$usernameField]) : null;
        $email = isset($employee[$emailField]) ? trim($employee[$emailField]) : null;
        $plainPassword = isset($employee[$passwordField]) ? trim($employee[$passwordField]) : null;
        
        if (empty($username)) {
            echo "  ⚠️  Skipping: No username found\n";
            $stats['skipped']++;
            continue;
        }
        
        if (empty($email)) {
            echo "  ⚠️  Skipping user '{$username}': No email found\n";
            $stats['skipped']++;
            continue;
        }
        
        if (empty($plainPassword)) {
            echo "  ⚠️  Skipping user '{$username}': No password found\n";
            $stats['skipped']++;
            continue;
        }
        
        // Hash the password
        $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT, $hash_options);
        
        // Escape values for SQL
        $usernameEscaped = addslashes($username);
        $emailEscaped = addslashes($email);
        $hashedPasswordEscaped = addslashes($hashedPassword);
        
        // Generate SQL UPDATE statement
        $sql = "UPDATE `{$config['table_name']}` \n";
        $sql .= "SET `{$config['password_column']}` = '{$hashedPasswordEscaped}', \n";
        $sql .= "    `{$config['username_column']}` = '{$usernameEscaped}' \n";
        $sql .= "WHERE `{$config['email_column']}` = '{$emailEscaped}';\n";
        $sql .= "-- User: {$username} | Email: {$email}\n\n";
        
        fwrite($sqlFile, $sql);
        
        echo "  ✅ Generated SQL for: {$username} ({$email})\n";
        $stats['sql_generated']++;
        $fileGenerated++;
    }
    
    echo "\nFile Summary:\n";
    echo "  Processed: {$fileProcessed}\n";
    echo "  SQL Generated: {$fileGenerated}\n";
    echo "\n";
}

// Write SQL file footer
fwrite($sqlFile, "-- ============================================================================\n");
fwrite($sqlFile, "-- End of Updates\n");
fwrite($sqlFile, "-- ============================================================================\n\n");
fwrite($sqlFile, "-- Review the updates above, then uncomment the line below to commit:\n");
fwrite($sqlFile, "-- COMMIT;\n\n");
fwrite($sqlFile, "-- If you need to rollback, uncomment the line below instead:\n");
fwrite($sqlFile, "-- ROLLBACK;\n\n");
fwrite($sqlFile, "SET SQL_SAFE_UPDATES = 1;\n");

fclose($sqlFile);

// ============================================================================
// FINAL STATISTICS
// ============================================================================

echo "=================================================\n";
echo "SQL Generation Completed!\n";
echo "=================================================\n";
echo "Total Processed: {$stats['total_processed']}\n";
echo "SQL Statements Generated: {$stats['sql_generated']}\n";
echo "Skipped: {$stats['skipped']}\n";

if (!empty($stats['errors'])) {
    echo "\nErrors:\n";
    foreach ($stats['errors'] as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";
echo "✅ SQL file generated: {$outputFile}\n";
echo "\n";
echo "=================================================\n";
echo "NEXT STEPS:\n";
echo "=================================================\n";
echo "1. Review the generated SQL file: {$outputFile}\n";
echo "2. Test on a backup database first (RECOMMENDED)\n";
echo "3. Execute the SQL file on your remote database\n";
echo "4. Uncomment 'COMMIT;' in the SQL file to save changes\n";
echo "5. DELETE this script and SQL file after success\n";
echo "=================================================\n";

?>
