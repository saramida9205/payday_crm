<?php
require_once __DIR__ . '/../common.php';

$sql = "ALTER TABLE contracts ADD COLUMN deferred_agreement_amount DECIMAL(15,2) DEFAULT 0 COMMENT '유예약정금'";

if (mysqli_query($link, $sql)) {
    echo "Column 'deferred_agreement_amount' added successfully.";
} else {
    echo "Error adding column: " . mysqli_error($link);
}
?>
