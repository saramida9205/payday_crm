<?php
require_once __DIR__ . '/../common.php';

// Check for admin permission (allow CLI)
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0)) {
    die("권한이 없습니다.");
}

echo "<h1>Certificate Template Migration</h1>";

// 1. Create Table
$sql = "CREATE TABLE IF NOT EXISTS certificate_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(100) NOT NULL,
    content LONGTEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($link, $sql)) {
    echo "<p style='color:green'>Table 'certificate_templates' created or already exists.</p>";
} else {
    die("<p style='color:red'>Error creating table: " . mysqli_error($link) . "</p>");
}

// 2. Migrate Existing Templates
$default_titles = get_default_certificate_titles(); // Currently in common.php
$count = 0;

foreach ($default_titles as $key => $title) {
    // Check if exists in DB
    $check_sql = "SELECT id FROM certificate_templates WHERE template_key = ?";
    $stmt = mysqli_prepare($link, $check_sql);
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) == 0) {
        // Not in DB, fetch from file
        $content = get_certificate_template($key); // Currently fetches from file
        if ($content === null) {
            $content = ''; // Empty if file doesn't exist
        }
        
        // Insert into DB
        $insert_sql = "INSERT INTO certificate_templates (template_key, title, content) VALUES (?, ?, ?)";
        $insert_stmt = mysqli_prepare($link, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "sss", $key, $title, $content);
        if (mysqli_stmt_execute($insert_stmt)) {
            echo "<p>Migrated: <strong>$title ($key)</strong></p>";
            $count++;
        } else {
            echo "<p style='color:red'>Failed to migrate: $title ($key) - " . mysqli_error($link) . "</p>";
        }
        mysqli_stmt_close($insert_stmt);
    } else {
        echo "<p style='color:gray'>Skipped: $title ($key) (Already in DB)</p>";
    }
    mysqli_stmt_close($stmt);
}

echo "<p><strong>Migration Completed. $count templates migrated.</strong></p>";
echo "<a href='../pages/certificate_print.php'>Go back to Certificate Print Page</a>";
?>
