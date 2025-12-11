<?php
require_once 'common.php';

echo "Tables:\n";
$result = mysqli_query($link, "SHOW TABLES");
while ($row = mysqli_fetch_row($result)) {
    echo $row[0] . "\n";
}

echo "\nStructure of sms_log:\n";
$result = mysqli_query($link, "DESCRIBE sms_log");
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
