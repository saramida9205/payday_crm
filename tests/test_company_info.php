<?php
require_once __DIR__ . '/../common.php';

echo "<h2>Company Info Verification Test</h2>";

// 1. Simulate saving settings (Direct DB Insert/Update as the process script does)
$test_data = [
    'biz_reg_number' => '111-22-33333',
    'loan_biz_reg_number' => '2024-Test-0001',
    'company_phone' => '010-1234-5678',
    'company_fax' => '02-9876-5432',
    'company_email' => 'test@example.com',
    'interest_account' => 'Test Bank 123-456-789',
    'expense_account' => 'Test Bank 987-654-321'
];

echo "<h3>1. Saving Test Data...</h3>";
foreach ($test_data as $key => $value) {
    $sql = "INSERT INTO company_info (info_key, info_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE info_value = VALUES(info_value)";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $key, $value);
    if (mysqli_stmt_execute($stmt)) {
        echo "Saved $key: $value<br>";
    } else {
        echo "Failed to save $key: " . mysqli_error($link) . "<br>";
    }
    mysqli_stmt_close($stmt);
}

// 2. Retrieve data using helper function
echo "<h3>2. Retrieving Data...</h3>";
$retrieved_info = get_all_company_info($link);

// 3. Verify
echo "<h3>3. Verification Results:</h3>";
$all_pass = true;
foreach ($test_data as $key => $expected_value) {
    $actual_value = $retrieved_info[$key] ?? '(not set)';
    if ($actual_value === $expected_value) {
        echo "<span style='color:green'>[PASS] $key: $actual_value</span><br>";
    } else {
        echo "<span style='color:red'>[FAIL] $key: Expected '$expected_value', got '$actual_value'</span><br>";
        $all_pass = false;
    }
}

if ($all_pass) {
    echo "<h4>All tests passed!</h4>";
} else {
    echo "<h4>Some tests failed.</h4>";
}

// Restore original values (Optional, but good practice if we knew them. For now just leaving test data or clearing)
// For this task, the user will likely overwrite them immediately via UI, so it's fine.
?>
