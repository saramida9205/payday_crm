<?php
require_once __DIR__ . '/../common.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_expense') {
        $contract_id = (int)$_POST['contract_id'];
        $expense_date = $_POST['expense_date'];
        $amount = (float)str_replace(',', '', $_POST['amount']);
        $description = trim($_POST['description']);
        $remarks = trim($_POST['remarks']);

        if (empty($contract_id) || empty($expense_date) || empty($amount) || empty($description)) {
            $_SESSION['error_message'] = "필수 항목을 모두 입력해주세요.";
            header("Location: ../pages/customer_detail.php?id=" . $_POST['customer_id']);
            exit;
        }

        mysqli_begin_transaction($link);
        try {
            // 1. Insert expense record
            $stmt = mysqli_prepare($link, "INSERT INTO contract_expenses (contract_id, expense_date, amount, description, remarks) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isdss", $contract_id, $expense_date, $amount, $description, $remarks);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("비용 등록 실패: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);

            // 2. Update contract shortfall amount - REMOVED per feedback
            // 비용은 부족금과 별도로 관리되므로 shortfall_amount를 증가시키지 않습니다.
            /*
            $stmt_update = mysqli_prepare($link, "UPDATE contracts SET shortfall_amount = shortfall_amount + ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_update, "di", $amount, $contract_id);
            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception("계약 부족금 업데이트 실패: " . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);
            */

            mysqli_commit($link);
            $_SESSION['message'] = "비용이 등록되었습니다.";

        } catch (Exception $e) {
            mysqli_rollback($link);
            $_SESSION['error_message'] = "오류 발생: " . $e->getMessage();
        }

        header("Location: ../pages/customer_detail.php?id=" . $_POST['customer_id']);
        exit;

    } elseif ($action === 'delete_expense') {
        $expense_id = (int)$_POST['expense_id'];
        $customer_id = (int)$_POST['customer_id'];

        if (empty($expense_id)) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
            exit;
        }

        // Check if expense exists and is not processed
        $stmt_check = mysqli_prepare($link, "SELECT contract_id, amount, is_processed FROM contract_expenses WHERE id = ?");
        mysqli_stmt_bind_param($stmt_check, "i", $expense_id);
        mysqli_stmt_execute($stmt_check);
        $result = mysqli_stmt_get_result($stmt_check);
        $expense = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt_check);

        if (!$expense) {
            echo json_encode(['success' => false, 'message' => '비용 정보를 찾을 수 없습니다.']);
            exit;
        }

        if ($expense['is_processed'] == 1) {
            echo json_encode(['success' => false, 'message' => '이미 수납 처리된 비용은 삭제할 수 없습니다.']);
            exit;
        }

        mysqli_begin_transaction($link);
        try {
            // 1. Delete expense record
            $stmt_delete = mysqli_prepare($link, "DELETE FROM contract_expenses WHERE id = ?");
            mysqli_stmt_bind_param($stmt_delete, "i", $expense_id);
            if (!mysqli_stmt_execute($stmt_delete)) {
                throw new Exception("비용 삭제 실패: " . mysqli_stmt_error($stmt_delete));
            }
            mysqli_stmt_close($stmt_delete);

            // 2. Update contract shortfall amount (Decrease) - REMOVED per feedback
            /*
            $stmt_update = mysqli_prepare($link, "UPDATE contracts SET shortfall_amount = shortfall_amount - ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_update, "di", $expense['amount'], $expense['contract_id']);
            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception("계약 부족금 업데이트 실패: " . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);
            */

            mysqli_commit($link);
            echo json_encode(['success' => true, 'message' => '비용이 삭제되었습니다.']);

        } catch (Exception $e) {
            mysqli_rollback($link);
            echo json_encode(['success' => false, 'message' => '오류 발생: ' . $e->getMessage()]);
        }
        exit;
    }
}
?>
