<?php
require_once __DIR__ . '/../common.php';

// Function to get overall statistics
function getOverallStats($link, $start_date = null, $end_date = null) {
    $stats = [];

    // --- 1. 조회 종료일(end_date) 기준 스냅샷 데이터 ---
    // 해당 날짜까지 실행된 대출 계약 ID 목록을 가져옵니다.
    $active_contracts_result = mysqli_query($link, "SELECT id FROM contracts WHERE loan_date <= '{$end_date}'");
    $contract_ids = [];
    while ($row = mysqli_fetch_assoc($active_contracts_result)) {
        $contract_ids[] = $row['id'];
    }
    $contract_id_list = !empty($contract_ids) ? implode(',', $contract_ids) : '0';

    // 스냅샷 기준 총 대출 원금 및 진행 건수
    $sql = "
        SELECT
            SUM(loan_amount) as total_loan_amount,
            COUNT(id) as active_contracts_count
        FROM contracts
        WHERE id IN ({$contract_id_list})
    ";
    $snapshot_stats = mysqli_fetch_assoc(mysqli_query($link, $sql));
    $stats['total_loan_amount'] = $snapshot_stats['total_loan_amount'] ?? 0;
    $stats['active_contracts_count'] = $snapshot_stats['active_contracts_count'] ?? 0;

    // 스냅샷 기준 총 회수 원금
    $sql_principal_collected = "SELECT SUM(amount) as total FROM collections WHERE contract_id IN ({$contract_id_list}) AND collection_type = '원금' AND deleted_at IS NULL AND collection_date <= '{$end_date}'";
    $stats['total_principal_collected'] = (float)mysqli_fetch_assoc(mysqli_query($link, $sql_principal_collected))['total'];

    // 스냅샷 기준 총 대출 잔액
    $stats['total_outstanding_balance'] = $stats['total_loan_amount'] - $stats['total_principal_collected'];

    // 스냅샷 기준 총 회수 이자
    $sql_interest_collected = "SELECT SUM(amount) as total FROM collections WHERE contract_id IN ({$contract_id_list}) AND collection_type = '이자' AND deleted_at IS NULL AND collection_date <= '{$end_date}'";
    $stats['total_interest_collected'] = (float)mysqli_fetch_assoc(mysqli_query($link, $sql_interest_collected))['total'];

    // --- 2. 조회 기간(start_date ~ end_date) 기준 데이터 ---
    $sql_period = "
        SELECT
            (SELECT SUM(loan_amount) FROM contracts WHERE loan_date BETWEEN ? AND ?) as new_contracts_amount_period,
            (SELECT COUNT(id) FROM contracts WHERE loan_date BETWEEN ? AND ?) as new_contracts_count_period,
            (SELECT SUM(CASE WHEN collection_type = '이자' THEN amount ELSE 0 END) FROM collections WHERE deleted_at IS NULL AND collection_date BETWEEN ? AND ?) as interest_collected_period,
            (SELECT SUM(CASE WHEN collection_type = '원금' THEN amount ELSE 0 END) FROM collections WHERE deleted_at IS NULL AND collection_date BETWEEN ? AND ?) as principal_collected_period,
            (SELECT SUM(CASE WHEN collection_type = '경비' THEN amount ELSE 0 END) FROM collections WHERE deleted_at IS NULL AND collection_date BETWEEN ? AND ?) as expense_deposits_period
    ";
    $stmt_period = mysqli_prepare($link, $sql_period);
    mysqli_stmt_bind_param($stmt_period, "ssssssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
    mysqli_stmt_execute($stmt_period);
    $period_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_period));
    mysqli_stmt_close($stmt_period);

    $stats = array_merge($stats, $period_stats);

    // --- 3. 기타 통계 ---
    // 총 고객 수 (현재 기준)
    $stats['total_customers_count'] = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM customers"))['total'] ?? 0;
    
    // 기간 내 총 입금액
    $stats['total_deposits'] = (float)$stats['interest_collected_period'] + (float)$stats['principal_collected_period'] + (float)$stats['expense_deposits_period'];

    // 기간 내 총 출금액 (신규 대출)
    $stats['total_withdrawals'] = $stats['new_contracts_amount_period'];

    return $stats;
}

?>