<?php
require_once __DIR__ . '/../common.php';

$response_message = '';
$success = false;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $response_message = "유효한 계약 ID가 제공되지 않았습니다.";
} else {
    $contract_id = (int)$_GET['id'];
    mysqli_begin_transaction($link);
    try {
        // Fetch contract details to get loan_date and agreement_date
        $stmt_get = mysqli_prepare($link, "SELECT loan_date, agreement_date FROM contracts WHERE id = ?");
        mysqli_stmt_bind_param($stmt_get, "i", $contract_id);
        mysqli_stmt_execute($stmt_get);
        $contract_result = mysqli_stmt_get_result($stmt_get);
        $contract = mysqli_fetch_assoc($contract_result);
        mysqli_stmt_close($stmt_get);

        if (!$contract) {
            throw new Exception("계약 정보를 찾을 수 없습니다.");
        }

        // Delete all related collections
        $stmt_delete = mysqli_prepare($link, "DELETE FROM collections WHERE contract_id = ?");
        mysqli_stmt_bind_param($stmt_delete, "i", $contract_id);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);

        // Recalculate the next_due_date based on the loan_date
        $exceptions = getHolidayExceptions();
        $initial_due_date = get_next_due_date(new DateTime($contract['loan_date']), (int)$contract['agreement_date'], $exceptions);
        $initial_due_date_str = $initial_due_date->format('Y-m-d');

        // Reset contract status and financial figures
        $sql_update = "UPDATE contracts SET status = 'active', next_due_date = ?, shortfall_amount = 0, last_interest_calc_date = NULL WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "si", $initial_due_date_str, $contract_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        mysqli_commit($link);
        $response_message = "계약 #{$contract_id}의 데이터가 성공적으로 초기화되었습니다.";
        $success = true;

    } catch (Exception $e) {
        mysqli_rollback($link);
        $response_message = "계약 #{$contract_id} 초기화 실패: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>계약 초기화</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f7f9; text-align: center; padding-top: 50px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; display: inline-block; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        p { font-size: 1.1em; margin-bottom: 20px; }
        button { background-color: #007bff; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-size: 1em; }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <p><?php echo htmlspecialchars($response_message); ?></p>
        <button onclick="closeAndRefresh()">확인</button>
    </div>

    <script>
        function closeAndRefresh() {
            <?php if ($success): ?>
            // Try to find the opener window and refresh it.
            if (window.opener && !window.opener.closed) {
                try {
                    window.opener.location.reload(true); // Force reload from server
                } catch (e) {
                    // This can fail due to cross-origin policies, but it's worth a try.
                    console.error("부모 창을 새로고침하는데 실패했습니다:", e);
                }
            }
            <?php endif; ?>
            // Close the current popup window.
            window.close();
        }

        // Automatically focus the button for better UX
        document.querySelector('button').focus();
    </script>
</body>
</html>