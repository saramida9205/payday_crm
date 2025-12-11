<?php
require_once __DIR__ . '/../common.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'errors' => []];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];

    if (empty($ids)) {
        $response['message'] = '삭제할 입금 내역 ID가 없습니다.';
        echo json_encode($response);
        exit();
    }

    // Validate and sanitize IDs
    $valid_ids = [];
    foreach ($ids as $id) {
        if (is_numeric($id) && $id > 0) {
            $valid_ids[] = (int)$id;
        } else {
            $response['errors'][] = "유효하지 않은 ID가 포함되어 있습니다: {$id}";
        }
    }

    if (empty($valid_ids)) {
        $response['message'] = '유효한 입금 내역 ID가 없습니다.';
        echo json_encode($response);
        exit();
    }

    mysqli_begin_transaction($link);
    try {
        $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
        $sql = "DELETE FROM collections WHERE id IN ({$placeholders})";

        if ($stmt = mysqli_prepare($link, $sql)) {
            $types = str_repeat('i', count($valid_ids));
            mysqli_stmt_bind_param($stmt, $types, ...$valid_ids);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("입금 내역 삭제 실패: " . mysqli_error($link));
            }
            $deleted_count = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($link);
            $response['success'] = true;
            $response['message'] = "총 {$deleted_count}건의 입금 내역이 성공적으로 삭제되었습니다.";
        } else {
            throw new Exception("SQL 준비 실패: " . mysqli_error($link));
        }

    } catch (Exception $e) {
        mysqli_rollback($link);
        $response['message'] = '입금 내역 삭제 중 오류가 발생했습니다.';
        $response['errors'][] = $e->getMessage();
    }
}

echo json_encode($response);
exit();

?>