<?php
require_once __DIR__ . '/config.php';
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$link) die("Connection failed");
$result = mysqli_query($link, "SELECT id FROM customers LIMIT 1");
if ($row = mysqli_fetch_assoc($result)) {
    echo $row['id'];
} else {
    echo "No customers found";
}
mysqli_close($link);
?>
