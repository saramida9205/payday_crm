<?php
require_once dirname(__DIR__) . '/config.php';

// Check connection
if (!$link) {
    die("Connection failed: " . mysqli_connect_error());
}

$sql = "ALTER TABLE `notices` 
        ADD COLUMN `file_path` VARCHAR(255) DEFAULT NULL COMMENT '첨부파일 경로' AFTER `view_count`,
        ADD COLUMN `file_name` VARCHAR(255) DEFAULT NULL COMMENT '첨부파일 원본명' AFTER `file_path`;";

if (mysqli_query($link, $sql)) {
    echo "Columns 'file_path' and 'file_name' added successfully.\n";
} else {
    // Check if duplicate column error (ignore if already exists)
    if (mysqli_errno($link) == 1060) {
        echo "Columns already exist.\n";
    } else {
        echo "Error altering table: " . mysqli_error($link) . "\n";
    }
}

mysqli_close($link);
