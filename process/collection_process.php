<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/contract_process.php';

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['action'])) {
    if ($_GET['action'] === 'get_last_collection_date') {
        header('Content-Type: application/json');
        $contract_id = (int)($_GET['contract_id'] ?? 0);
        $response = ['success' => false, 'last_collection_date' => null];

        if ($contract_id > 0) {
            $stmt = mysqli_prepare($link, "SELECT MAX(collection_date) as last_date FROM collections WHERE contract_id = ? AND deleted_at IS NULL");
            mysqli_stmt_bind_param($stmt, "i", $contract_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            if ($row && $row['last_date']) {
                $response['success'] = true;
                $response['last_collection_date'] = $row['last_date'];
            }
            mysqli_stmt_close($stmt);
        }
        echo json_encode($response);
        exit();
    }
} elseif ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit('Invalid request method.');
}

$action = $_POST['action'] ?? '';

if ($action === 'calculate') {
    header('Content-Type: application/json');
    $response = [];
    try {
        $contract_id = (int)($_POST['contract_id'] ?? 0);
        $collection_date_str = $_POST['collection_date'] ?? '';
        $total_amount_input = (float)($_POST['total_amount'] ?? 0);

        if (empty($contract_id) || empty($collection_date_str)) {
            throw new Exception("계산에 필요한 계약 또는 회수일 정보가 없습니다.");
        }

        $stmt = mysqli_prepare($link, "SELECT * FROM contracts WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $contract_id);
        mysqli_stmt_execute($stmt);
        $contract_result = mysqli_stmt_get_result($stmt);
        $contract = mysqli_fetch_assoc($contract_result);
        mysqli_stmt_close($stmt);

        if (!$contract) throw new Exception("계약 정보를 찾을 수 없습니다.");

        // Use the current state directly from the contracts table for consistency
        $outstanding_principal = (float)$contract['current_outstanding_principal'];
        $existing_shortfall = (float)$contract['shortfall_amount'];
        $last_interest_calc_date = $contract['last_interest_calc_date'] ?? $contract['loan_date'];

        $interest_data = calculateAccruedInterest($link, $contract, $collection_date_str);
        $accrued_interest = $interest_data['total'];

        // [NEW] Get unpaid expenses
        $stmt_exp = mysqli_prepare($link, "SELECT SUM(amount) as total FROM contract_expenses WHERE contract_id = ? AND is_processed = 0");
        mysqli_stmt_bind_param($stmt_exp, "i", $contract_id);
        mysqli_stmt_execute($stmt_exp);
        $unpaid_expenses = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_exp))['total'];
        mysqli_stmt_close($stmt_exp);

        // Calculate automatic distribution for the preview
        $amount_available = $total_amount_input;
        
        $interest_to_pay = $existing_shortfall + $accrued_interest;
        $interest_payment = min($amount_available, $interest_to_pay);
        $amount_available -= $interest_payment;
        
        $principal_payment = ($amount_available > 0) ? min($amount_available, $outstanding_principal) : 0;

        $expected_new_shortfall = ($existing_shortfall + $accrued_interest) - $interest_payment;

        $response = [
            'success' => true,
            'outstanding_principal' => $outstanding_principal,
            'accrued_interest' => $accrued_interest,
            'interest_period_start' => $last_interest_calc_date,
            'interest_period_days' => (new DateTime($collection_date_str))->diff(new DateTime($last_interest_calc_date))->days,
            'shortfall_amount' => $existing_shortfall,
            'expected_new_shortfall' => $expected_new_shortfall > 0 ? floor($expected_new_shortfall) : 0,
            'interest_payment' => $interest_payment,
            'interest_payment' => $interest_payment,
            'principal_payment' => $principal_payment,
            'unpaid_expenses' => $unpaid_expenses
        ];

    } catch (Exception $e) {
        http_response_code(400);
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    echo json_encode($response);
    exit();
}

if ($action === 'save_collection') {
    $contract_id = (int)($_POST['contract_id'] ?? 0);
    $collection_date_str = $_POST['collection_date'] ?? '';
    $memo = trim($_POST['memo'] ?? '');
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $expense_payment = (float)($_POST['expense_payment'] ?? 0);
    $expense_memo = trim($_POST['expense_memo'] ?? '');
    $interest_payment = (float)($_POST['interest_payment'] ?? 0);
    $principal_payment = (float)($_POST['principal_payment'] ?? 0);

    mysqli_begin_transaction($link);
    try {
        // --- 백업 로직 추가 ---
        // process_collection 함수 내부에서 사용할 transaction_id를 미리 생성합니다.
        $transaction_id = uniqid('txn_', true);
        create_contract_state_backup($link, $contract_id, $transaction_id);

        // Call the new centralized function to handle everything
        if (!process_collection(
            $link,
            $contract_id,
            $collection_date_str,
            $total_amount,
            $expense_payment,
            $interest_payment,
            $principal_payment,
            $memo,
            $expense_memo,
            $transaction_id // 미리 생성한 transaction_id 전달
        )) {
            // The exception will be thrown from within process_collection
            // but we keep this for logical clarity.
            throw new Exception("입금 처리 함수 실행에 실패했습니다.");
        }

        mysqli_commit($link);
        $_SESSION['message'] = "입금 처리가 완료되었습니다.";

    } catch (Exception $e) {
        mysqli_rollback($link);
        $_SESSION['error_message'] = "입금 처리 실패: " . $e->getMessage();
        error_log("Collection save failed for contract_id {" . $contract_id . "}: " . $e->getMessage() . " Stack: " . $e->getTraceAsString());
    }
    header("Location: ../pages/collection_manage.php?contract_id=" . $contract_id);
    exit();
}

if ($action === 'calculate_expected_interest') {
    header('Content-Type: application/json');
    $response = [];
    try {
        $contract_id = (int)($_POST['contract_id'] ?? 0);
        $target_date_str = $_POST['target_date'] ?? '';

        if (empty($contract_id) || empty($target_date_str)) {
            throw new Exception("계산에 필요한 계약 또는 기준일 정보가 없습니다.");
        }

        $stmt = mysqli_prepare($link, "SELECT * FROM contracts WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $contract_id);
        mysqli_stmt_execute($stmt);
        $contract_result = mysqli_stmt_get_result($stmt);
        $contract = mysqli_fetch_assoc($contract_result);
        mysqli_stmt_close($stmt);

        if (!$contract) throw new Exception("계약 정보를 찾을 수 없습니다.");

        $interest_data = calculateAccruedInterest($link, $contract, $target_date_str);

        $response = [
            'success' => true,
            'accrued_interest' => $interest_data['total'],
            'interest_period_start' => $interest_data['interest_period_start'],
            'target_date' => $target_date_str
        ];

    } catch (Exception $e) {
        http_response_code(400);
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    echo json_encode($response);
    exit();
}

if ($action === 'delete_single' || $action === 'delete_bulk') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    $collection_ids_str = $_POST['ids'] ?? '';
    if (empty($collection_ids_str)) {
        $response['message'] = '삭제할 항목이 지정되지 않았습니다.';
        echo json_encode($response);
        exit();
    }
    // 단일 삭제든 벌크 삭제든 첫 번째 ID를 기준으로 처리합니다. (UI상 마지막 거래만 삭제 가능)
    $collection_id = (int)explode(',', $collection_ids_str)[0];

    mysqli_begin_transaction($link);
    try {
        // 1. 삭제할 입금 내역의 정보 조회 (transaction_id, contract_id)
        $stmt_info = mysqli_prepare($link, "SELECT contract_id, transaction_id FROM collections WHERE id = ?");
        mysqli_stmt_bind_param($stmt_info, "i", $collection_id);
        mysqli_stmt_execute($stmt_info);
        $collection_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));
        mysqli_stmt_close($stmt_info);

        if (!$collection_info || empty($collection_info['transaction_id'])) {
            throw new Exception("삭제할 입금 내역 정보(트랜잭션 ID)를 찾을 수 없습니다.");
        }
        $contract_id = $collection_info['contract_id'];
        $transaction_id = $collection_info['transaction_id'];

        // 2. 해당 계약의 마지막 입금 내역이 맞는지 확인
        $stmt_last = mysqli_prepare($link, "SELECT transaction_id FROM collections WHERE contract_id = ? AND deleted_at IS NULL ORDER BY collection_date DESC, id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt_last, "i", $contract_id);
        mysqli_stmt_execute($stmt_last);
        $last_transaction_id = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_last))['transaction_id'];
        mysqli_stmt_close($stmt_last);

        if ($transaction_id !== $last_transaction_id) {
            throw new Exception("가장 마지막 입금 내역만 삭제할 수 있습니다.");
        }

        // 3. 백업 데이터로 계약 상태 복원
        $stmt_restore = mysqli_prepare($link, "UPDATE contracts c JOIN contract_state_backups b ON c.id = b.contract_id SET c.status = b.status, c.current_outstanding_principal = b.current_outstanding_principal, c.shortfall_amount = b.shortfall_amount, c.next_due_date = b.next_due_date, c.last_interest_calc_date = b.last_interest_calc_date WHERE b.collection_transaction_id = ?");
        mysqli_stmt_bind_param($stmt_restore, "s", $transaction_id);
        if (!mysqli_stmt_execute($stmt_restore)) throw new Exception("계약 상태 복원 실패: " . mysqli_stmt_error($stmt_restore));
        mysqli_stmt_close($stmt_restore);

        // 4. 입금 내역 영구 삭제
        $stmt_delete_coll = mysqli_prepare($link, "DELETE FROM collections WHERE transaction_id = ?");
        mysqli_stmt_bind_param($stmt_delete_coll, "s", $transaction_id);
        if (!mysqli_stmt_execute($stmt_delete_coll)) throw new Exception("입금 내역 삭제 실패: " . mysqli_stmt_error($stmt_delete_coll));
        $deleted_count = mysqli_stmt_affected_rows($stmt_delete_coll);
        mysqli_stmt_close($stmt_delete_coll);

        // 5. 사용된 백업 데이터 삭제
        $stmt_delete_backup = mysqli_prepare($link, "DELETE FROM contract_state_backups WHERE collection_transaction_id = ?");
        mysqli_stmt_bind_param($stmt_delete_backup, "s", $transaction_id);
        if (!mysqli_stmt_execute($stmt_delete_backup)) throw new Exception("백업 데이터 삭제 실패: " . mysqli_stmt_error($stmt_delete_backup));
        mysqli_stmt_close($stmt_delete_backup);

        mysqli_commit($link);
        $response['success'] = true;
        $response['message'] = "입금 내역이 삭제되고 계약 상태가 복원되었습니다.";

    } catch (Exception $e) {
        mysqli_rollback($link);
        $response['message'] = "삭제 처리 중 오류 발생: " . $e->getMessage();
        error_log("Collection deletion failed: " . $e->getMessage());
    }
    echo json_encode($response);
    exit();
}

if ($action === 'restore_bulk') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    $ids_to_restore = [];

    $ids_str = $_POST['ids'] ?? '';
    if (!empty($ids_str)) {
        $ids_to_restore = array_filter(explode(',', $ids_str), 'is_numeric');
    }

    if (empty($ids_to_restore)) {
        $response['message'] = '복원할 항목이 지정되지 않았습니다.';
        echo json_encode($response);
        exit();
    }

    mysqli_begin_transaction($link);
    try {
        $placeholders = implode(',', array_fill(0, count($ids_to_restore), '?'));
        $types = str_repeat('i', count($ids_to_restore));

        // Get contract_ids before restoring
        $stmt_get_contract = mysqli_prepare($link, "SELECT DISTINCT contract_id FROM collections WHERE id IN ({$placeholders})");
        mysqli_stmt_bind_param($stmt_get_contract, $types, ...$ids_to_restore);
        mysqli_stmt_execute($stmt_get_contract);
        $result_contracts = mysqli_stmt_get_result($stmt_get_contract);
        $contract_ids = [];
        while($row = mysqli_fetch_assoc($result_contracts)){
            $contract_ids[] = $row['contract_id'];
        }
        mysqli_stmt_close($stmt_get_contract);

        // [NEW] Rollback expenses linked to these collections
        // 삭제되는 수납 내역에 연결된 비용들을 다시 '미처리' 상태로 되돌립니다.
        $stmt_rollback = mysqli_prepare($link, "UPDATE contract_expenses SET is_processed = 0, processed_date = NULL, linked_collection_id = NULL WHERE linked_collection_id IN ({$placeholders})");
        mysqli_stmt_bind_param($stmt_rollback, $types, ...$ids_to_restore);
        mysqli_stmt_execute($stmt_rollback);
        mysqli_stmt_close($stmt_rollback);

        // Restore the collections
        $stmt_restore = mysqli_prepare($link, "UPDATE collections SET deleted_at = NULL, deleted_by = NULL WHERE id IN ({$placeholders})");
        mysqli_stmt_bind_param($stmt_restore, $types, ...$ids_to_restore);
        mysqli_stmt_execute($stmt_restore);
        $restored_count = mysqli_stmt_affected_rows($stmt_restore);
        mysqli_stmt_close($stmt_restore);

        // Recalculate state for each affected contract
        foreach (array_unique($contract_ids) as $contract_id) {
            recalculate_and_update_contract_state($link, $contract_id);
        }

        mysqli_commit($link);
        $response['success'] = true;
        $response['message'] = "총 {$restored_count}건의 입금 내역이 성공적으로 복원되었습니다.";
    } catch (Exception $e) {
        mysqli_rollback($link);
        $response['message'] = "복원 처리 중 오류 발생: " . $e->getMessage();
    }
    echo json_encode($response);
    exit();
}

if ($action === 'delete_permanently') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    $ids_to_delete = [];

    $ids_str = $_POST['ids'] ?? '';
    if (!empty($ids_str)) {
        $ids_to_delete = array_filter(explode(',', $ids_str), 'is_numeric');
    }

    if (empty($ids_to_delete)) {
        $response['message'] = '영구 삭제할 항목이 지정되지 않았습니다.';
        echo json_encode($response);
        exit();
    }

    mysqli_begin_transaction($link);
    try {
        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
        $types = str_repeat('i', count($ids_to_delete));

        // Get contract_ids before deleting permanently to recalculate state later
        $stmt_get_contract = mysqli_prepare($link, "SELECT DISTINCT contract_id FROM collections WHERE id IN ({$placeholders})");
        mysqli_stmt_bind_param($stmt_get_contract, $types, ...$ids_to_delete);
        mysqli_stmt_execute($stmt_get_contract);
        $result_contracts = mysqli_stmt_get_result($stmt_get_contract);
        $contract_ids = [];
        while($row = mysqli_fetch_assoc($result_contracts)){
            $contract_ids[] = $row['contract_id'];
        }
        mysqli_stmt_close($stmt_get_contract);

        // Permanently delete the collections
        $stmt_delete = mysqli_prepare($link, "DELETE FROM collections WHERE id IN ({$placeholders})");
        mysqli_stmt_bind_param($stmt_delete, $types, ...$ids_to_delete);
        if (!mysqli_stmt_execute($stmt_delete)) {
            throw new Exception("영구 삭제 실행에 실패했습니다: " . mysqli_stmt_error($stmt_delete));
        }
        $deleted_count = mysqli_stmt_affected_rows($stmt_delete);
        mysqli_stmt_close($stmt_delete);

        // Recalculate state for each affected contract
        foreach (array_unique($contract_ids) as $contract_id) {
            if (!recalculate_and_update_contract_state($link, $contract_id)) {
                // Log the error but don't stop the whole process, as the main deletion was successful.
                error_log("Failed to update contract state for contract ID {$contract_id} after permanent deletion.");
            }
        }

        mysqli_commit($link);
        $response['success'] = true;
        $response['message'] = "총 {$deleted_count}건의 입금 내역이 성공적으로 영구 삭제되었습니다.";

    } catch (Exception $e) {
        mysqli_rollback($link);
        $response['message'] = "영구 삭제 처리 중 오류 발생: " . $e->getMessage();
    }
    echo json_encode($response);
    exit();
}

exit('No valid action specified.');
?>