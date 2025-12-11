<?php
require_once '../common.php';

$sql = "CREATE TABLE IF NOT EXISTS holidays (
    holiday_date DATE PRIMARY KEY,
    description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($link, $sql)) {
    echo "Table 'holidays' created successfully.";
} else {
    echo "Error creating table: " . mysqli_error($link);
}
?>
