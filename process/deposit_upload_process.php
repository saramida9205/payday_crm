<?php
ini_set('display_errors', 0); // Disable error display to client
ini_set('log_errors', 1); // Enable error logging
error_reporting(E_ALL); // Report all errors to log

// Ensure no output before JSON
ob_start();

require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/../lib/SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

// Clear buffer just in case includes had output
ob_clean();
header('Content-Type: application/json');

$action = $_POST['action'] ?? ($json_data['action'] ?? '');
if (empty($action) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['action'])) {
        $action = $input['action'];
        $_POST = array_merge($_POST, $input);
    }
}

if ($action === 'upload') {
    $response = ['success' => false, 'data' => [], 'message' => ''];

    try {
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != UPLOAD_ERR_OK) {
            throw new Exception("파일 업로드 오류");
        }

        $xlsx = SimpleXLSX::parse($_FILES['excel_file']['tmp_name']);
        if (!$xlsx) {
            throw new Exception(SimpleXLSX::parseError());
        }

        $rows = $xlsx->rows();
        // Assuming headers are in the first row. We need to find columns: "거래일자", "입금금액" (or "맡기신금액"), "의뢰인/수취인" (or "기재내용", "적요")
        // Woori Bank Excel format usually has headers around row 1-3. Need to detect.
        // Simple strategy: Look for "거래일자" and map columns.

        $header_idx = -1;
        $col_map = []; // 'date' => idx, 'depositor' => idx, 'amount' => idx

        /** @var array $rows */
        foreach ($rows as $idx => $row) {
            foreach ($row as $cidx => $cell) {
                $cell = trim($cell);
                // Date: '거래일자' or '거래일시'
                if ((strpos($cell, '거래일자') !== false || strpos($cell, '거래일시') !== false) && !isset($col_map['date'])) {
                    $header_idx = $idx;
                    $col_map['date'] = $cidx;
                }
                // Depositor: '의뢰인', '내용', '적요', or '기재내용'
                if ((strpos($cell, '의뢰인') !== false || strpos($cell, '메모') !== false || strpos($cell, '입금자') !== false || strpos($cell, '기재내용') !== false) && !isset($col_map['depositor'])) {
                    $col_map['depositor'] = $cidx;
                }
                // Amount: '입금금액', '맡기신금액', or '입금(원)'
                if ((strpos($cell, '입금금액') !== false || strpos($cell, '맡기신금액') !== false || strpos($cell, '입금(원)') !== false) && !isset($col_map['amount'])) {
                    $col_map['amount'] = $cidx;
                }
            }
            if ($header_idx !== -1 && isset($col_map['date']) && isset($col_map['depositor']) && isset($col_map['amount'])) break;
        }

        if ($header_idx === -1 || !isset($col_map['date']) || !isset($col_map['amount'])) {
            // Fallback: If no headers found, maybe it's raw data? Or standard columns?
            // Let's assume standard Woori format column indices if detection fails,
            // BUT for safety, throw error asking for standard format.
            throw new Exception("엑셀 형식을 인식할 수 없습니다. '거래일자', '입금금액'('맡기신금액'), '의뢰인'('입금자') 열이 필요합니다.");
        }

        // Process rows
        $data_rows = [];
        for ($i = $header_idx + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $date_raw = trim($r[$col_map['date']] ?? '');

            // Basic date validation (YYYY.MM.DD or YYYY-MM-DD)
            $date_clean = str_replace('.', '-', $date_raw);
            // Handle "2024.01.01 12:00:00" -> "2024-01-01"
            $date_parts = explode(' ', $date_clean);
            $date = $date_parts[0];

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue; // Skip invalid dates

            $depositor = isset($col_map['depositor']) ? trim($r[$col_map['depositor']]) : '';
            $amount_raw = trim($r[$col_map['amount']]);
            $amount = (float)str_replace(',', '', $amount_raw);

            if ($amount <= 0) continue; // Skip withdrawals or zero

            // Matching Logic
            $match_info = findMatch($link, $depositor, $amount);

            // Check for duplicate (Same Contract + Same Date + Same Amount)
            if ($match_info['contract_id']) {
                $check_sql = "SELECT id FROM collections WHERE contract_id = ? AND collection_date = ? AND amount = ? AND deleted_at IS NULL";
                $stmt_check = mysqli_prepare($link, $check_sql);
                mysqli_stmt_bind_param($stmt_check, "isd", $match_info['contract_id'], $date, $amount);
                mysqli_stmt_execute($stmt_check);
                if (mysqli_stmt_fetch($stmt_check)) {
                    // Found duplicate
                    $match_info['status'] = 'DUPLICATE';
                }
                mysqli_stmt_close($stmt_check);
            }

            $data_rows[] = [
                'date' => $date,
                'depositor' => $depositor,
                'amount' => $amount,
                'status_code' => $match_info['status'],
                'contract_id' => $match_info['contract_id'],
                'customer_name' => $match_info['customer_name'],
                'contract_info' => $match_info['contract_info'],
                'memo' => $match_info['memo']
            ];
        }

        $response['success'] = true;
        $response['data'] = $data_rows;
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

if ($action === 'process') {
    $log_file = __DIR__ . '/deposit_debug.log';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Processing started\n", FILE_APPEND);

    $response = ['success' => false, 'message' => '', 'errors' => []];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['deposits']) || !is_array($input['deposits'])) {
        file_put_contents($log_file, "No data found in input\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => '데이터가 없습니다.']);
        exit;
    }

    $deposits = $input['deposits'];
    file_put_contents($log_file, "Received " . count($deposits) . " deposits\n", FILE_APPEND);
    $success_count = 0;

    try {
        // Check DB connection
        if (!$link) throw new Exception("Database connection lost");

        mysqli_begin_transaction($link);
        foreach ($deposits as $idx => $d) {
            $contract_id = (int)$d['contract_id'];
            $date = $d['date'];
            $amount = (float)$d['amount'];
            $memo = $d['memo'];

            if (!$contract_id) {
                $response['errors'][] = "Row {$idx}: 계약 번호 없음";
                continue;
            }

            file_put_contents($log_file, "Row {$idx}: Calling getContractById($contract_id)\n", FILE_APPEND);

            // Fetch Contract & Calc Logic (Replicating Bulk Process Logic)
            $contract = getContractById($link, $contract_id);
            if (!$contract) {
                $response['errors'][] = "Row {$idx}: 계약 정보 없음 (ID: $contract_id)";
                continue;
            }

            // Calculate Interest Split
            $running_balance = (float)$contract['current_outstanding_principal'];
            $cumulative_shortfall = (float)$contract['shortfall_amount'];
            $last_calc_date = $contract['last_interest_calc_date'] ?? $contract['loan_date'];

            // Check for previous transaction on same day handled by process_collection?
            // process_collection handles basic validation.
            // We need to calculate how much is Interest vs Principal.

            // Note: Since we are processing list, one contract might appear multiple times.
            // But getting current state from DB is safest for sequential processing?
            // NO. If we process Row 1, DB updates. Then Row 2 for same contract fetches NEW DB state.
            // So we can rely on DB state being fresh for each iteration if we commit?
            // process_collection does NOT commit internally?
            // Wait, common.php process_collection does NOT have transaction begin/commit.
            // It just executes queries.
            // So queries are executed in the current transaction scope.
            // But we need to be careful: `getContractById` fetches from DB.
            // Does MySQL seeing uncommitted changes in same transaction? YES (Read Uncommitted or typically safe in same transaction).

            // Calculate interest
            file_put_contents($log_file, "Row {$idx}: Calling calculateAccruedInterest\n", FILE_APPEND);
            $interest_data = calculateAccruedInterest($link, $contract, $date);
            $accrued_interest = $interest_data['total'];

            $total_due = $cumulative_shortfall + $accrued_interest; // Interest + Shortfall

            $interest_payment = min($amount, $total_due);
            $interest_payment = max(0, $interest_payment);
            $principal_payment = min($amount - $interest_payment, $running_balance);
            $principal_payment = max(0, $principal_payment);

            // Remaining amount (overpayment) check?
            // process_collection throws if principal_payment > outstanding.

            // uniqid('txn_', true) is too long (27 chars). Use simpler ID or check DB. 
            // Use time-based random to ensure uniqueness but keep short.
            // txn_ + 13 chars = 17 chars. Safe for VARCHAR(20).
            $txn_id = uniqid('txn_');

            // Log progress
            file_put_contents($log_file, "Processing Row {$idx}: ID {$contract_id}\n", FILE_APPEND);

            // Call process_collection
            try {
                file_put_contents($log_file, "Row {$idx}: Calling process_collection with txn_id $txn_id\n", FILE_APPEND);
                process_collection(
                    $link,
                    $contract_id,
                    $date,
                    $amount,
                    0, // expense_payment
                    $interest_payment,
                    $principal_payment,
                    $memo,
                    '', // expense_memo
                    $txn_id
                );
                $success_count++;
            } catch (Throwable $e) {
                file_put_contents($log_file, "Error on Row {$idx}: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
                $response['errors'][] = "Row {$idx} ({$d['depositor']}): " . $e->getMessage();
                throw $e;
            }
        }

        mysqli_commit($link);
        file_put_contents($log_file, "Commit successful. Success count: {$success_count}\n", FILE_APPEND);

        $response['success'] = true;
        $response['message'] = "총 {$success_count}건의 입금이 처리되었습니다.";
    } catch (Throwable $e) {
        if ($link) mysqli_rollback($link);
        file_put_contents($log_file, "Rollback due to: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
        $response['success'] = false;
        $response['message'] = "처리 실패: " . $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

// Helper Function for Matching
function findMatch($link, $depositor, $amount)
{
    // 1. Exact Name Match
    // Clean depositor name (remove spaces?)
    $name = trim($depositor);

    // Search Customers
    $sql = "SELECT c.id as contract_id, cu.name as customer_name, c.product_name, c.status
        FROM customers cu
        JOIN contracts c ON cu.id = c.customer_id
        WHERE cu.name = ? AND c.status = 'active'";

    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $matches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $matches[] = $row;
    }

    if (count($matches) === 1) {
        return [
            'status' => 'OK',
            'contract_id' => $matches[0]['contract_id'],
            'customer_name' => $matches[0]['customer_name'],
            'contract_info' => '[' . $matches[0]['contract_id'] . '] ' . $matches[0]['customer_name'] . ' (' . $matches[0]['product_name'] . ')',
            'memo' => $depositor
        ];
    } elseif (count($matches) > 1) {
        // Multiple contracts active. Check amount?
        // TODO: Advanced logic (e.g. check monthly payment amount)
        return [
            'status' => 'MULTIPLE',
            'contract_id' => null,
            'customer_name' => $matches[0]['customer_name'],
            'contract_info' => '다중 계약 존재',
            'memo' => $depositor
        ];
    }

    // No exact match? Try partial?
    // For now return Error
    return [
        'status' => 'NONE',
        'contract_id' => null,
        'customer_name' => null,
        'contract_info' => '',
        'memo' => $depositor
    ];
}
