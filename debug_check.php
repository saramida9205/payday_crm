<?php
require_once __DIR__ . '/config.php';
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

$contract_id = 26;

echo "<h3>Contract Expenses</h3>";
$res = mysqli_query($link, "SELECT * FROM contract_expenses WHERE contract_id = $contract_id");
while ($row = mysqli_fetch_assoc($res)) {
    echo "<pre>" . print_r($row, true) . "</pre>";
}

echo "<h3>Collections</h3>";
$res = mysqli_query($link, "SELECT * FROM collections WHERE contract_id = $contract_id ORDER BY id DESC LIMIT 5");
while ($row = mysqli_fetch_assoc($res)) {
    echo "<pre>" . print_r($row, true) . "</pre>";
}
?>
