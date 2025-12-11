<?php
require_once __DIR__ . '/common.php';
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

$contract_id = 73; // Customer 45
$customer_id = 45;

// Helper to get shortfall
function getShortfall($link, $id) {
    $res = mysqli_query($link, "SELECT shortfall_amount FROM contracts WHERE id = $id");
    return (float)mysqli_fetch_assoc($res)['shortfall_amount'];
}

echo "<h3>Test Start: Contract $contract_id</h3>";

// 1. Initial State
$initial_shortfall = getShortfall($link, $contract_id);
echo "Initial Shortfall: " . number_format($initial_shortfall) . "<br>";

// 2. Add Expense
echo "<h4>Adding Expense (1000)</h4>";
// Simulate expense_process.php logic
$amount = 1000;
mysqli_query($link, "INSERT INTO contract_expenses (contract_id, expense_date, amount, description, is_processed) VALUES ($contract_id, NOW(), $amount, 'Test Fix', 0)");
$expense_id = mysqli_insert_id($link);

$after_add_shortfall = getShortfall($link, $contract_id);
echo "Shortfall after add: " . number_format($after_add_shortfall) . " (Expected: " . number_format($initial_shortfall) . ") - ";
echo ($initial_shortfall == $after_add_shortfall) ? "<span style='color:green'>PASS</span>" : "<span style='color:red'>FAIL</span>";
echo "<br>";

// 3. Process Collection
echo "<h4>Processing Collection (Pay 1000 for Expense)</h4>";
$transaction_id = uniqid('test_fix_', true);
try {
    mysqli_begin_transaction($link);
    process_collection(
        $link,
        $contract_id,
        date('Y-m-d'),
        1000, // Total
        1000, // Expense
        0, 0,
        "Test Fix Payment",
        "Expense Pay",
        $transaction_id
    );
    mysqli_commit($link);
    echo "Collection Processed.<br>";
} catch (Exception $e) {
    mysqli_rollback($link);
    echo "Error: " . $e->getMessage();
}

// Check Expense Status
$res = mysqli_query($link, "SELECT * FROM contract_expenses WHERE id = $expense_id");
$exp = mysqli_fetch_assoc($res);
echo "Expense Processed: " . $exp['is_processed'] . " (Expected: 1)<br>";
echo "Linked Collection ID: " . $exp['linked_collection_id'] . "<br>";
$collection_id = $exp['linked_collection_id'];

// 4. Delete Collection (Rollback Test)
echo "<h4>Deleting Collection (Rollback Test)</h4>";
// Simulate collection_process.php delete logic
// We need to call the delete logic. Since it's in a file that handles POST, let's simulate the query logic directly or include the file if possible.
// But collection_process.php is an action handler. Let's just run the queries that we added to collection_process.php to verify they work.

mysqli_begin_transaction($link);
// Rollback expenses
$stmt_rollback = mysqli_prepare($link, "UPDATE contract_expenses SET is_processed = 0, processed_date = NULL, linked_collection_id = NULL WHERE linked_collection_id = ?");
mysqli_stmt_bind_param($stmt_rollback, "i", $collection_id);
mysqli_stmt_execute($stmt_rollback);

// Delete collection
mysqli_query($link, "UPDATE collections SET deleted_at = NOW() WHERE id = $collection_id");
mysqli_commit($link);

// Verify Rollback
$res = mysqli_query($link, "SELECT * FROM contract_expenses WHERE id = $expense_id");
$exp = mysqli_fetch_assoc($res);
echo "Expense Processed after Delete: " . $exp['is_processed'] . " (Expected: 0) - ";
echo ($exp['is_processed'] == 0) ? "<span style='color:green'>PASS</span>" : "<span style='color:red'>FAIL</span>";
echo "<br>";

// Cleanup
mysqli_query($link, "DELETE FROM contract_expenses WHERE id = $expense_id");
// mysqli_query($link, "DELETE FROM collections WHERE id = $collection_id"); // Already soft deleted
?>
