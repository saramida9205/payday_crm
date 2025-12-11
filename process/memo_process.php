<?php
require_once __DIR__ . '/../common.php';

// --- Save or Update Memo ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_memo'])) {
    $contract_id = $_POST['contract_id'];
    $memo_text = trim($_POST['memo_text']);
    $color = $_POST['color'] ?? 'black';
    $memo_id = $_POST['memo_id'] ?? null; // For updates
    $is_ajax = isset($_POST['ajax']);
    $created_by = $_SESSION['username'] ?? 'system';

    if (!empty($contract_id) && !empty($memo_text)) {
        if (empty($memo_id)) { // New memo
            $sql = "INSERT INTO contract_memos (contract_id, memo_text, color, created_by) VALUES (?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "isss", $contract_id, $memo_text, $color, $created_by);
            }
        } else { // Update existing memo
            $sql = "UPDATE contract_memos SET memo_text = ?, color = ?, updated_at = NOW() WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssi", $memo_text, $color, $memo_id);
            }
        }

        if (isset($stmt) && mysqli_stmt_execute($stmt)) {
            $response = ['success' => true, 'message' => "메모가 성공적으로 저장되었습니다."];
            mysqli_stmt_close($stmt);
        } else {
            $response = ['success' => false, 'message' => "메모 저장에 실패했습니다: " . (isset($stmt) ? mysqli_stmt_error($stmt) : mysqli_error($link))];
        }
    } else {
        $response = ['success' => false, 'message' => "필수 입력 항목이 누락되었습니다."];
    }

    // Redirect back
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        if ($response['success']) {
            $_SESSION['message'] = $response['message'];
        } else {
            $_SESSION['error_message'] = $response['message'];
        }
        $customer_id = $_POST['customer_id'];
        header("location: ../pages/customer_detail.php?id=" . $customer_id);
     }
    exit();
}

// --- Delete Memo ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_memo'])) {
    $memo_id = $_POST['memo_id'];
    $customer_id = $_POST['customer_id'];
    $is_ajax = isset($_POST['ajax']);

    if (!empty($memo_id)) {
        $sql = "DELETE FROM contract_memos WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $memo_id);
            if (mysqli_stmt_execute($stmt)) {
                $response = ['success' => true, 'message' => "메모가 삭제되었습니다."];
            } else {
                $response = ['success' => false, 'message' => "메모 삭제에 실패했습니다: " . mysqli_stmt_error($stmt)];
            }
            mysqli_stmt_close($stmt);
        } else {
            $response = ['success' => false, 'message' => "DB 준비에 실패했습니다: " . mysqli_error($link)];
        }
    } else {
        $response = ['success' => false, 'message' => "삭제할 메모 ID가 없습니다."];
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        if ($response['success']) {
            $_SESSION['message'] = $response['message'];
        } else {
            $_SESSION['error_message'] = $response['message'];
        }
        header("location: ../pages/customer_detail.php?id=" . $customer_id);
    }
    exit();
}

// --- Manage Frequent Memos ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_frequent_memo'])) {
        $memo_text = trim($_POST['frequent_memo_text']);
        if (!empty($memo_text)) {
            $sql = "INSERT INTO frequent_memos (memo_text) VALUES (?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $memo_text);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        // Redirect back to the referring page or a default
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    if (isset($_POST['delete_frequent_memo'])) {
        $memo_id = $_POST['frequent_memo_id'];
        if (!empty($memo_id)) {
            $sql = "DELETE FROM frequent_memos WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $memo_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
}
?>