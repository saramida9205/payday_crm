<?php
require_once __DIR__ . '/../config.php';
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$link) die("Connection failed");

$sql = "ALTER TABLE contract_expenses ADD COLUMN remarks TEXT";
if (mysqli_query($link, $sql)) {
    echo "Column 'remarks' added successfully.";
} else {
    echo "Error adding column: " . mysqli_error($link);
}
mysqli_close($link);
?>
