<?php
require_once __DIR__ . '/../common.php';

// --- AJAX/Test Mode Check ---
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$is_test_mode = isset($_GET['mode']) && $_GET['mode'] === 'test';
// This script should be run daily by a cron job.

function run_daily_batch() {
    global $link, $is_test_mode;
    $response = ['success' => false, 'message' => ''];
    
    try {
        // 1. 연체 대상으로 의심되는 계약 ID들을 먼저 조회합니다.
        $today = date('Y-m-d');
        $sql_select = "SELECT id FROM contracts WHERE status = 'active' AND next_due_date IS NOT NULL AND next_due_date < ?";
        $stmt_select = mysqli_prepare($link, $sql_select);
        mysqli_stmt_bind_param($stmt_select, "s", $today);
        mysqli_stmt_execute($stmt_select);
        $result = mysqli_stmt_get_result($stmt_select);
        $overdue_candidates = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_select);

        $updated_count = 0;
        $failed_contracts = [];

        // 2. 각 계약에 대해 상태를 재계산하고 업데이트합니다.
        // 이렇게 하면 status 뿐만 아니라 shortfall, next_due_date 등 모든 상태가 최신으로 유지됩니다.
        foreach ($overdue_candidates as $candidate) {
            $contract_id = $candidate['id'];
            // recalculate_and_update_contract_state 함수는 내부적으로 트랜잭션을 관리합니다.
            if (recalculate_and_update_contract_state($link, $contract_id)) {
                $updated_count++;
            } else {
                $failed_contracts[] = $contract_id;
            }
        }

        $log_message = "일일 배치 처리가 완료되었습니다. 총 {$updated_count}건의 계약 상태를 재계산 및 업데이트했습니다.";
        if (!empty($failed_contracts)) {
            $log_message .= " 실패한 계약 ID: " . implode(', ', $failed_contracts);
            error_log("Daily batch failed for contracts: " . implode(', ', $failed_contracts));
        }

        // 3. 마지막 실행 시간 기록
        $execution_time = date('Y-m-d H:i:s');
        update_company_info($link, 'last_daily_batch_run', $execution_time);

        $response['success'] = true;
        $response['message'] = $log_message;

    } catch (Exception $e) {
        // Log the main error
        error_log("Daily batch process failed: " . $e->getMessage());
        $response['message'] = "일일 배치 처리 실패: " . $e->getMessage();
    }

    return $response;
}

// Execute the batch process
$result = run_daily_batch();

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode($result);
} else {
    echo $result['message'] . "\n";
}

?>