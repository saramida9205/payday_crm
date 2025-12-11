<?php
require_once '../common.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Certificate Template Migration</h1>";

// 1. Create Table
$sql_create = "CREATE TABLE IF NOT EXISTS certificate_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(100) NOT NULL,
    content MEDIUMTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($link, $sql_create)) {
    echo "<p style='color:green'>[SUCCESS] Table 'certificate_templates' created or already exists.</p>";
} else {
    die("<p style='color:red'>[ERROR] Failed to create table: " . mysqli_error($link) . "</p>");
}

// 2. Migrate Existing Templates
// We use the existing function from common.php which currently reads from files.
// After migration, we will update common.php to read from DB.
$templates = get_all_certificate_templates();

$count = 0;
foreach ($templates as $template) {
    $key = $template['template_key'];
    $title = $template['title'];
    $content = $template['content'];

    // Check if exists
    $check_sql = "SELECT id FROM certificate_templates WHERE template_key = ?";
    $stmt_check = mysqli_prepare($link, $check_sql);
    mysqli_stmt_bind_param($stmt_check, "s", $key);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        echo "<p>[SKIP] Template '$title' ($key) already exists in DB.</p>";
    } else {
        $insert_sql = "INSERT INTO certificate_templates (template_key, title, content) VALUES (?, ?, ?)";
        $stmt_insert = mysqli_prepare($link, $insert_sql);
        mysqli_stmt_bind_param($stmt_insert, "sss", $key, $title, $content);
        
        if (mysqli_stmt_execute($stmt_insert)) {
            echo "<p style='color:green'>[INSERT] Template '$title' ($key) migrated successfully.</p>";
            $count++;
        } else {
            echo "<p style='color:red'>[ERROR] Failed to insert '$title' ($key): " . mysqli_stmt_error($stmt_insert) . "</p>";
        }
        mysqli_stmt_close($stmt_insert);
    }
    mysqli_stmt_close($stmt_check);
}

echo "<h3>Migration Completed. $count templates inserted.</h3>";
echo "<p>Please delete this file from the server after verification.</p>";
?>
