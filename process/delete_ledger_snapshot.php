<?php
require_once __DIR__ . '/../common.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 최고 관리자만 접근 가능
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

global $link;
$response = ['success' => false, 'message' => '잘못된 요청입니다.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_snapshots') {
    if (empty($_POST['snapshot_dates'])) {
        $response['message'] = '삭제할 스냅샷을 선택해주세요.';
    } else {
        $snapshot_dates = $_POST['snapshot_dates'];
        $placeholders = implode(',', array_fill(0, count($snapshot_dates), '?'));
        $types = str_repeat('s', count($snapshot_dates));

        $sql = "DELETE FROM bond_ledger_snapshots WHERE snapshot_date IN ($placeholders)";
        $stmt = mysqli_prepare($link, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$snapshot_dates);
            if (mysqli_stmt_execute($stmt)) {
                $deleted_count = mysqli_stmt_affected_rows($stmt);
                $response['success'] = true;
                $response['message'] = "선택한 스냅샷 데이터가 영구적으로 삭제되었습니다. (총 {$deleted_count}개 스냅샷 기준일)";
            } else {
                $response['message'] = '스냅샷 삭제 중 오류가 발생했습니다: ' . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $response['message'] = 'SQL 준비 중 오류가 발생했습니다: ' . mysqli_error($link);
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;