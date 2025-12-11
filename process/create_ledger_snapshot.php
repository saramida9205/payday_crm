<?php
// 오류 보고 수준을 최대로 설정하고, 화면에 오류를 표시하지 않도록 설정
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1); // 오류를 로그 파일에 기록

// AJAX 요청인지 확인
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// 스크립트 종료 시 치명적 오류를 확인하는 함수 등록
register_shutdown_function('fatal_error_handler');

function fatal_error_handler() {
    global $is_ajax;
    $error = error_get_last();
    // 치명적인 오류가 발생했고, 아직 응답이 전송되지 않은 경우
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // AJAX 요청일 경우에만 JSON 응답을 보냄
        if ($is_ajax && !headers_sent()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "치명적 오류 발생: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']
            ]);
        }
        exit();
    }
}

require_once __DIR__ . '/../common.php';

// 스크립트 실행 시간 제한을 해제하고 메모리 제한을 늘립니다.
set_time_limit(0);
ini_set('memory_limit', '512M');

function create_bond_ledger_snapshot() {
    global $link, $is_ajax;
    $response = ['success' => false, 'message' => ''];

    // 데이터베이스 연결 확인
    if (!$link || mysqli_connect_errno()) {
        $response['message'] = '데이터베이스 연결에 실패했습니다: ' . mysqli_connect_error();
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            echo $response['message'];
        }
        exit();
    }

    mysqli_begin_transaction($link);

    try {
        $snapshot_datetime = date('Y-m-d H:i:s');

        // 1. 모든 계약의 원금 상환 총액을 미리 계산
        $principal_payments_query = mysqli_query($link, "SELECT contract_id, SUM(amount) as total_paid FROM collections WHERE collection_type = '원금' AND deleted_at IS NULL GROUP BY contract_id");
        if (!$principal_payments_query) {
            throw new Exception("원금 상환액 조회 실패: " . mysqli_error($link));
        }
        $principal_payments = [];
        while ($row = mysqli_fetch_assoc($principal_payments_query)) {
            $principal_payments[$row['contract_id']] = (float)$row['total_paid'];
        }

        // 2. 모든 계약 정보 조회
        $sql = "SELECT 
                    c.id as contract_id, c.customer_id, c.product_name, c.loan_amount, c.loan_date, 
                    c.maturity_date, c.agreement_date, c.interest_rate, c.overdue_interest_rate, 
                    c.repayment_method, c.status, c.next_due_date, c.last_interest_calc_date,
                    cu.name as customer_name,
                    cu.phone as customer_phone,
                    cu.address_registered
                FROM contracts c
                JOIN customers cu ON c.customer_id = cu.id                
                ORDER BY c.loan_date DESC";
        
        $result = mysqli_query($link, $sql);
        if (!$result) {
            throw new Exception("계약 정보 조회 실패: " . mysqli_error($link));
        }

        $insert_sql = "INSERT INTO bond_ledger_snapshots (
            snapshot_date, contract_id, customer_name, product_name, customer_phone, address_registered, 
            loan_amount, loan_date, maturity_date, agreement_date, interest_rate, overdue_interest_rate, 
            repayment_method, outstanding_principal, overdue_days, status, next_due_date, last_interest_calc_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_insert = mysqli_prepare($link, $insert_sql);
        if (!$stmt_insert) {
            throw new Exception("SQL 준비 실패: " . mysqli_error($link));
        }

        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $processed_count = 0;

        // 3. 각 계약을 순회하며 스냅샷 데이터 생성
        while ($contract = mysqli_fetch_assoc($result)) {
            $principal_paid = $principal_payments[$contract['contract_id']] ?? 0;
            $outstanding_principal = (float)$contract['loan_amount'] - $principal_paid;

            $overdue_days = 0;
            if ($contract['status'] === 'overdue' && !empty($contract['next_due_date'])) {
                $next_due_date = new DateTime($contract['next_due_date']);
                if ($today > $next_due_date) {
                    $overdue_days = $today->diff($next_due_date)->days;
                }
            }

            $repayment_method = $contract['repayment_method'] ?? '';
            if (empty($repayment_method) || strtolower($repayment_method) === 'bullet') {
                $repayment_method = '자유상환';
            }

            $maturity_date = $contract['maturity_date'];
            if (!is_string($maturity_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $maturity_date)) {
                $maturity_date = null; // YYYY-MM-DD 형식이 아니면 NULL로 설정
            }
            
            $agreement_date_str = $contract['agreement_date'];
            if (!is_string($agreement_date_str) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $agreement_date_str)) {
                $agreement_date_str = null; // YYYY-MM-DD 형식이 아니면 NULL로 설정
            }

            mysqli_stmt_bind_param($stmt_insert, "sisssdssssddsdisss",
                $snapshot_datetime,
                $contract['contract_id'],
                $contract['customer_name'],
                $contract['product_name'],
                $contract['customer_phone'],
                $contract['address_registered'],
                $contract['loan_amount'],
                $contract['loan_date'],
                $maturity_date,
                $agreement_date_str,
                $contract['interest_rate'],
                $contract['overdue_interest_rate'],
                $repayment_method,
                $outstanding_principal,
                $overdue_days,
                $contract['status'],
                $contract['next_due_date'],
                $contract['last_interest_calc_date']
            );

            if (!mysqli_stmt_execute($stmt_insert)) {
                throw new Exception("스냅샷 데이터 삽입 실패 (계약 ID: {" . $contract['contract_id'] . "}): " . mysqli_stmt_error($stmt_insert));
            }
            $processed_count++;
        }

        mysqli_stmt_close($stmt_insert);
        mysqli_commit($link);

        $response['success'] = true;
        $response['message'] = "{$processed_count}건의 계약에 대한 스냅샷을 성공적으로 생성했습니다.";

    } catch (Throwable $e) { // Exception 대신 Throwable을 사용하여 PHP 7+의 모든 오류를 처리
        if ($link) mysqli_rollback($link);
        $response['message'] = "스냅샷 생성 중 오류 발생: " . $e->getMessage();
        error_log("Snapshot creation failed: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    }

    // 항상 응답을 출력하도록 finally 블록처럼 사용
    if ($is_ajax) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode($response);
    } else {
        echo $response['message'] . "\n";
    }
    exit();
}

create_bond_ledger_snapshot();
?>
