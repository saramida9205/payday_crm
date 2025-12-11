<?php
require_once 'config.php';

$sql = "ALTER TABLE collections ADD COLUMN transaction_id VARCHAR(255) NULL DEFAULT NULL AFTER id";

if (mysqli_query($link, $sql)) {
    echo "Table 'collections' altered successfully.";
} else {
    echo "ERROR: Could not alter table 'collections'. " . mysqli_error($link);
}

mysqli_close($link);
?>