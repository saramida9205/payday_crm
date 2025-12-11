<?php
require_once __DIR__ . '/../config.php';
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$link) die("Connection failed");

$sql = "ALTER TABLE contract_expenses ADD COLUMN linked_collection_id INT NULL DEFAULT NULL AFTER processed_date";
if (mysqli_query($link, $sql)) {
    echo "Column 'linked_collection_id' added successfully.";
} else {
    echo "Error adding column: " . mysqli_error($link);
}
mysqli_close($link);
?>
