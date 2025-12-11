<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/process/contract_process.php'; // Ensure dependencies are loaded if needed, though common.php might be enough if it includes them.
// Actually common.php doesn't include contract_process.php usually?
// Let's check collection_process.php includes.
// collection_process.php includes header.php -> common.php.
// And it calls process_collection.
// process_collection is in common.php (I added it there? No, I modified it in common.php).
// Wait, process_collection was in common.php?
// Yes, I viewed common.php and it was there.

$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

$contract_id = 26;
$collection_date = date('Y-m-d');
$total_amount = 1000;
$expense_payment = 1000;
$interest_payment = 0;
$principal_payment = 0;
$memo = "Backend Verification";
$expense_memo = "Test Expense Payment";
$transaction_id = uniqid('test_', true);

echo "<h3>Before</h3>";
$res = mysqli_query($link, "SELECT * FROM contract_expenses WHERE contract_id = $contract_id");
while ($row = mysqli_fetch_assoc($res)) {
    echo "Expense ID: {$row['id']}, Processed: {$row['is_processed']}<br>";
}

try {
    mysqli_begin_transaction($link);
    process_collection(
        $link,
        $contract_id,
        $collection_date,
        $total_amount,
        $expense_payment,
        $interest_payment,
        $principal_payment,
        $memo,
        $expense_memo,
        $transaction_id
    );
    mysqli_commit($link);
    echo "<h3>Process Collection Success</h3>";
} catch (Exception $e) {
    mysqli_rollback($link);
    echo "<h3>Error: " . $e->getMessage() . "</h3>";
}

echo "<h3>After</h3>";
$res = mysqli_query($link, "SELECT * FROM contract_expenses WHERE contract_id = $contract_id");
while ($row = mysqli_fetch_assoc($res)) {
    echo "Expense ID: {$row['id']}, Processed: {$row['is_processed']}<br>";
}
?>
