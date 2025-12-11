<?php
require_once __DIR__ . '/../common.php';

// Function to get all condition changes with contract and customer details
function getConditionChanges($link) {
    $sql = "SELECT cc.*, cu.name AS customer_name, con.loan_amount 
            FROM condition_changes cc
            JOIN contracts con ON cc.contract_id = con.id
            JOIN customers cu ON con.customer_id = cu.id
            ORDER BY cc.change_date DESC";
    $result = mysqli_query($link, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to get all active contracts for dropdown
function getActiveContracts($link) {
    $sql = "SELECT 
                c.id, 
                -- Use the new rate if it exists and is active, otherwise use the original rate.
                IF(c.new_interest_rate IS NOT NULL AND c.rate_change_date IS NOT NULL, c.new_interest_rate, c.interest_rate) as current_interest_rate,
                IF(c.new_overdue_rate IS NOT NULL AND c.rate_change_date IS NOT NULL, c.new_overdue_rate, c.overdue_interest_rate) as current_overdue_rate,
                c.maturity_date, 
                c.agreement_date, 
                c.next_due_date, 
                CONCAT(cu.name, ' - ', c.loan_amount, '원 (', c.loan_date, ')') AS contract_info 
            FROM contracts c 
            JOIN customers cu ON c.customer_id = cu.id 
            WHERE c.status IN ('active', 'overdue') 
            ORDER BY cu.name ASC";
    $result = mysqli_query($link, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Initialize variables
$contract_id = $change_date = $new_interest_rate = $new_maturity_date = $reason = $new_agreement_date = $new_next_due_date = "";
$old_interest_rate = $old_overdue_interest_rate = $old_maturity_date = $old_agreement_date = "";
$update = false;
$id = 0;

// Add Condition Change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {
    $contract_id = $_POST['contract_id'];
    $change_date = $_POST['change_date'];
    $new_interest_rate = $_POST['new_interest_rate'];
    $new_overdue_rate = $_POST['new_overdue_rate'];
    $new_maturity_date = $_POST['new_maturity_date'];
    $new_agreement_date = $_POST['new_agreement_date'];
    $new_next_due_date = $_POST['new_next_due_date'];
    $reason = $_POST['reason'];

    // Get old values from the contract
    $contract_query = mysqli_query($link, "SELECT * FROM contracts WHERE id = $contract_id");
    $contract_data = mysqli_fetch_assoc($contract_query);

    // Get current rates (could be original or new rates)
    $old_interest_rate = (float)($contract_data['new_interest_rate'] ?? $contract_data['interest_rate']);
    $old_overdue_interest_rate = (float)($contract_data['new_overdue_rate'] ?? $contract_data['overdue_interest_rate']);
    $old_maturity_date = $contract_data['maturity_date'];
    $old_agreement_date = $contract_data['agreement_date'];

    // If new values are not provided, use the old ones.
    $final_new_interest_rate = !empty($new_interest_rate) ? $new_interest_rate : $old_interest_rate;
    $final_new_overdue_rate = !empty($new_overdue_rate) ? $new_overdue_rate : $old_overdue_interest_rate;
    $final_new_maturity_date = !empty($new_maturity_date) ? $new_maturity_date : $old_maturity_date;
    $final_new_agreement_date = !empty($new_agreement_date) ? $new_agreement_date : $old_agreement_date;

    // Start transaction
    mysqli_begin_transaction($link);

    try {
        // 1. Insert into condition_changes
        $sql_insert = "INSERT INTO condition_changes (contract_id, change_date, old_interest_rate, new_interest_rate, old_overdue_rate, new_overdue_rate, old_agreement_date, new_agreement_date, old_maturity_date, new_maturity_date, new_next_due_date, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($link, $sql_insert);
        mysqli_stmt_bind_param($stmt_insert, "isddddiissss", $contract_id, $change_date, $old_interest_rate, $final_new_interest_rate, $old_overdue_interest_rate, $final_new_overdue_rate, $old_agreement_date, $final_new_agreement_date, $old_maturity_date, $final_new_maturity_date, $new_next_due_date, $reason);
        mysqli_stmt_execute($stmt_insert);

        // 2. Update contracts table
        // '다음 입금 예정일'은 사용자가 입력한 경우에만 업데이트하고, 그렇지 않으면 NULL로 설정하여
        // recalculate_and_update_contract_state 함수가 자동으로 계산하도록 합니다.
        $next_due_date_to_set = !empty($new_next_due_date) ? $new_next_due_date : null;

        $sql_update = "UPDATE contracts SET rate_change_date = ?, new_interest_rate = ?, new_overdue_rate = ?, maturity_date = ?, agreement_date = ?, next_due_date = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($link, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "sddsssi", $change_date, $final_new_interest_rate, $final_new_overdue_rate, $final_new_maturity_date, $final_new_agreement_date, $next_due_date_to_set, $contract_id);
        mysqli_stmt_execute($stmt_update);

        // 3. Recalculate contract state to apply changes immediately
        // 사용자가 '새 다음 입금 예정일'을 직접 지정하지 않은 경우에만 재계산을 통해 자동 설정합니다.
        if (!recalculate_and_update_contract_state($link, $contract_id, false, $contract_data)) {
             throw new Exception("계약 상태 재계산에 실패했습니다.");
        }

        // Commit transaction
        mysqli_commit($link);
        $_SESSION['message'] = "계약 조건이 성공적으로 변경되었습니다.";
        header("location: ../pages/condition_change_manage.php");

    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($link);
        echo "오류가 발생했습니다. 잠시 후 다시 시도해주세요.";
        // throw $exception;
    }
}

?>