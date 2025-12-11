<?php
require_once '../common.php';

// 1. Add 'type' column if not exists
$result = mysqli_query($link, "SHOW COLUMNS FROM holidays LIKE 'type'");
if (mysqli_num_rows($result) == 0) {
    $sql = "ALTER TABLE holidays ADD COLUMN type ENUM('holiday', 'workday') DEFAULT 'holiday'";
    if (mysqli_query($link, $sql)) {
        echo "Added 'type' column.<br>";
    } else {
        echo "Error adding column: " . mysqli_error($link) . "<br>";
    }
}

// 2. Remove Weekend entries (description = '토요일' or '일요일')
// This cleans up the previous population so weekends are handled by logic, not DB.
$sql = "DELETE FROM holidays WHERE description IN ('토요일', '일요일')";
if (mysqli_query($link, $sql)) {
    echo "Removed weekend entries from DB (rows affected: " . mysqli_affected_rows($link) . ").<br>";
} else {
    echo "Error deleting weekends: " . mysqli_error($link) . "<br>";
}

echo "DB Update Complete.";
?>
