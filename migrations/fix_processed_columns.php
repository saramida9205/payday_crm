<?php
require_once __DIR__ . '/../config.php';
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$link) die("Connection failed");

$sql1 = "ALTER TABLE contract_expenses ADD COLUMN is_processed TINYINT(1) DEFAULT 0";
$sql2 = "ALTER TABLE contract_expenses ADD COLUMN processed_date DATETIME NULL";

if (mysqli_query($link, $sql1)) {
    echo "Column 'is_processed' added successfully.<br>";
} else {
    echo "Error adding 'is_processed': " . mysqli_error($link) . "<br>";
}

if (mysqli_query($link, $sql2)) {
    echo "Column 'processed_date' added successfully.<br>";
} else {
    echo "Error adding 'processed_date': " . mysqli_error($link) . "<br>";
}

mysqli_close($link);
?>
