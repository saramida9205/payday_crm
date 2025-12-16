<?php
// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set timezone to Korea Standard Time for all date/time functions
date_default_timezone_set('Asia/Seoul');

// Include config file
require_once "config.php";

// Slack Webhook URL 설정 (실제 URL로 교체해야 합니다. 예: https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX)
define('SLACK_WEBHOOK_URL', 'YOUR_SLACK_WEBHOOK_URL');

// --- Holidays in South Korea for the current year ---
// Fetch holiday exceptions (holidays on weekdays, workdays on weekends)
function getHolidayExceptions()
{
    global $link;
    $data = ['holidays' => [], 'workdays' => []];

    if (!$link) return $data;

    $current_year = date('Y');
    $next_year = $current_year + 1;

    $sql = "SELECT holiday_date, type FROM holidays WHERE YEAR(holiday_date) BETWEEN ? AND ?";
    $stmt = mysqli_prepare($link, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $current_year, $next_year);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['type'] == 'workday') {
                $data['workdays'][] = $row['holiday_date'];
            } else {
                $data['holidays'][] = $row['holiday_date'];
            }
        }
        mysqli_stmt_close($stmt);
    }
    return $data;
}

// Check if a date is a holiday
function isHoliday($date_str, $exceptions = null)
{
    if ($exceptions === null) {
        $exceptions = getHolidayExceptions();
    }

    // 1. Check explicit workday exception
    if (in_array($date_str, $exceptions['workdays'])) {
        return false;
    }

    // 2. Check explicit holiday exception
    if (in_array($date_str, $exceptions['holidays'])) {
        return true;
    }

    // 3. Default: Weekend is holiday
    $w = date('w', strtotime($date_str));
    return ($w == 0 || $w == 6);
}

// Helper function to check for leap year
function is_leap_year($year)
{
    return (date('L', mktime(0, 0, 0, 1, 1, $year)) == 1);
}

function get_status_display($status)
{
    $status_text = '';
    switch ($status) {
        case 'active':
            $status_text = '<span style="color: green;">정상</span>';
            break;
        case 'paid':
            $status_text = '<span style="color: blue;">완납</span>';
            break;
        case 'defaulted':
            $status_text = '<span style="color: grey;">부실</span>';
            break;
        case 'overdue':
            $status_text = '<span style="color: red; font-weight: bold;">연체</span>';
            break;
        default:
            $status_text = htmlspecialchars($status);
            break;
    }
    return $status_text;
}

/**
 * SMS 발송 상태에 따라 적절한 HTML 배지를 반환합니다.
 * @param string $status 'sent', 'failed', 'pending' 등
 * @return string HTML badge
 */
function getStatusBadge($status)
{
    switch ($status) {
        case 'sent':
            return '<span class="badge bg-success">발송성공</span>';
        case 'failed':
            return '<span class="badge bg-danger">발송실패</span>';
        case 'pending':
            return '<span class="badge bg-warning text-dark">결과대기</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

/**
 * Sends a notification to a Slack channel using a webhook.
 * @param string|array $payload The message string or a full payload array for Slack Block Kit.
 * @param string $webhookUrl The Slack incoming webhook URL.
 * @return bool True on success, false on failure.
 */
function sendSlackNotification($payload, $webhookUrl = SLACK_WEBHOOK_URL)
{
    global $link; // 데이터베이스 연결을 사용하기 위해 global로 선언

    // 슬랙 알림 설정 값 확인
    $company_info = get_all_company_info($link);
    if (($company_info['slack_notifications_enabled'] ?? '1') !== '1') {
        return false; // 설정이 '사용 안함'이면 즉시 종료
    }

    if (empty($webhookUrl) || $webhookUrl === 'YOUR_SLACK_WEBHOOK_URL') {
        error_log("Slack Webhook URL is not configured in common.php.");
        return false;
    }

    if (is_string($payload)) {
        $payload = ['text' => $payload];
    }

    $payload = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);


    if ($result === 'ok' && $http_code === 200) {
        return true;
    }
    error_log("Slack notification failed. HTTP Code: {$http_code}, Response: {$result}, Error: {$curl_error}");
    return false;
}

/**
 * 와이드샷 SMS API를 사용하여 메시지를 발송합니다.
 * @param array $data 발송 데이터 (userKey, receiverTelNo, contents, callback 등)
 * @param string $type 메시지 타입 (sms, lms, mms 등)
 * @return array API 응답 결과
 */
function sendSmsApi($data, $type = 'sms')
{
    $url = WIDESHOT_API_URL . '/api/v1/message/' . $type;

    $headers = [
        'sejongApiKey: ' . WIDESHOT_API_KEY
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);


    if ($curl_error) {
        return ['success' => false, 'message' => 'cURL Error: ' . $curl_error];
    }

    $responseData = json_decode($response, true);

    if ($http_code == 200 && isset($responseData['code']) && $responseData['code'] == '200') {
        return ['success' => true, 'data' => $responseData];
    } else {
        $error_message = $responseData['message'] ?? $response;
        return ['success' => false, 'message' => "API Error (HTTP: {$http_code}): " . $error_message, 'data' => $responseData];
    }
}

/**
 * 와이드샷 API를 사용하여 SMS 발송 결과를 확인합니다.
 * @param string $sendCode 발송 시 사용된 userKey
 * @return array API 응답 결과
 */
function checkSmsResultApi($sendCode)
{
    $url = WIDESHOT_API_URL . '/api/v3/message/result?sendCode=' . urlencode($sendCode);

    $headers = [
        'sejongApiKey: ' . WIDESHOT_API_KEY
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);


    if ($curl_error) {
        return ['success' => false, 'message' => 'cURL Error: ' . $curl_error];
    }

    $responseData = json_decode($response, true);
    return ['success' => ($http_code == 200), 'data' => $responseData, 'message' => $responseData['message'] ?? ''];
}


function getCollections($link, $start_date = '', $end_date = '', $customer_name = '', $contract_id = '', $fetch_deleted = false)
{
    $sql = "SELECT 
                coll.id, 
                coll.contract_id, 
                coll.collection_date, 
                coll.collection_type, 
                coll.amount, 
                coll.memo,
                coll.generated_interest,
                coll.transaction_id,
                coll.deleted_at,
                coll.deleted_by,
                cust.id as customer_id,
                cust.name as customer_name
            FROM collections coll
            JOIN contracts con ON coll.contract_id = con.id
            JOIN customers cust ON con.customer_id = cust.id";

    $sql .= $fetch_deleted ? " WHERE coll.deleted_at IS NOT NULL" : " WHERE coll.deleted_at IS NULL";

    $params = [];
    $types = '';

    if (!empty($start_date)) {
        $sql .= " AND coll.collection_date >= ?";
        $params[] = $start_date;
        $types .= 's';
    }
    if (!empty($end_date)) {
        $sql .= " AND coll.collection_date <= ?";
        $params[] = $end_date;
        $types .= 's';
    }
    if (!empty($customer_name)) {
        $sql .= " AND cust.name LIKE ?";
        $params[] = '%' . $customer_name . '%';
        $types .= 's';
    }
    if (!empty($contract_id)) {
        $sql .= " AND coll.contract_id = ?";
        $params[] = $contract_id;
        $types .= 'i';
    }

    $sql .= " ORDER BY coll.collection_date DESC, coll.id DESC";

    $stmt = mysqli_prepare($link, $sql);
    if ($stmt && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $collections = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $collections;
}

function getActiveContractsForDropdown($link)
{
    $sql = "SELECT 
                c.id, 
                cu.name as customer_name, 
                c.loan_amount 
            FROM contracts c
            JOIN customers cu ON c.customer_id = cu.id
            WHERE c.status IN ('active', 'overdue')
            ORDER BY cu.name ASC, c.id ASC";
    $result = mysqli_query($link, $sql);
    $contracts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['contract_info'] = htmlspecialchars($row['customer_name'] . ' (계약번호: ' . $row['id'] . ', 대출원금: ' . number_format($row['loan_amount']) . '원)');
        $contracts[] = $row;
    }
    return $contracts;
}

/**
 * Fetches the current outstanding principal from the contracts table.
 * This value is maintained by recalculate_and_update_contract_state.
 *
 * @param mysqli $link The database connection.
 * @param int $contract_id The ID of the contract.
 * @return float The current outstanding principal.
 */
function calculateOutstandingPrincipal($link, $contract_id)
{
    $stmt = mysqli_prepare($link, "SELECT current_outstanding_principal FROM contracts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $contract_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $contract = mysqli_fetch_assoc($result);
    return (float)($contract['current_outstanding_principal'] ?? 0.0);
}

/**
 * 증명서 발급을 위해 계약 테이블에 저장된 현재 상태 값을 직접 가져옵니다.
 */
function getContractStateForCertificate($link, $contract_id)
{
    $stmt = mysqli_prepare($link, "SELECT current_outstanding_principal, shortfall_amount, last_interest_calc_date FROM contracts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $contract_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

function calculatePrincipalPaidAsOf($link, $contract_id, $as_of_date)
{
    $stmt = mysqli_prepare($link, "SELECT SUM(amount) as total FROM collections WHERE contract_id = ? AND collection_type = '원금' AND deleted_at IS NULL AND collection_date <= ?");
    mysqli_stmt_bind_param($stmt, "is", $contract_id, $as_of_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $principal_paid = (float)mysqli_fetch_assoc($result)['total'];
    return $principal_paid;
}

/**변경전 function calculateAccruedInterestForPeriod($link, $contract, $principal_at_start_of_period, $start_date_str, $end_date_str, $current_due_date_str, $existing_shortfall = 0) {  */
/**
 * Fetches the history of interest rate changes for a contract.
 * Returns an array of periods with their effective rates.
 * 
 * @param mysqli $link
 * @param int $contract_id
 * @param array $contract Contract data (optional, to avoid re-fetching if available)
 * @return array Array of ['start_date' => 'YYYY-MM-DD', 'interest_rate' => float, 'overdue_rate' => float]
 */
function get_interest_rate_history($link, $contract_id, $contract = null)
{
    if (!$contract) {
        $contract = getContractById($link, $contract_id);
    }

    // 1. Initial State
    $history = [];
    $history[] = [
        'start_date' => $contract['loan_date'],
        'interest_rate' => (float)$contract['interest_rate'],
        'overdue_rate' => (float)$contract['overdue_interest_rate']
    ];

    // 2. Fetch changes from condition_changes table
    $sql = "SELECT change_date, new_interest_rate, new_overdue_rate FROM condition_changes WHERE contract_id = ? ORDER BY change_date ASC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $contract_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        // Only add if rates are actually defined
        if (!is_null($row['new_interest_rate'])) {
            $history[] = [
                'start_date' => $row['change_date'],
                'interest_rate' => (float)$row['new_interest_rate'],
                'overdue_rate' => (float)$row['new_overdue_rate']
            ];
        }
    }

    return $history;
}

function calculateAccruedInterestForPeriod($link, $contract, $principal, $start_date_str, $end_date_str, $due_date_str)
{

    $start_date = new DateTime($start_date_str ?? 'now');
    $end_date = new DateTime($end_date_str ?? 'now');

    if ($end_date <= $start_date) {
        return ['normal' => 0, 'overdue' => 0, 'total' => 0, 'details' => []];
    }

    $due_date = new DateTime($due_date_str ?? 'now');

    // [NEW] Get full rate history
    $rate_history = get_interest_rate_history($link, $contract['id'], $contract);

    $normal_interest = 0;
    $overdue_interest = 0;
    $details = [];

    // Define calculation checkpoints based on start/end dates, due date, and rate changes
    $checkpoints = [$start_date];
    if ($due_date > $start_date && $due_date < $end_date) {
        $checkpoints[] = $due_date;
    }

    foreach ($rate_history as $change) {
        $change_date = new DateTime($change['start_date']);
        if ($change_date > $start_date && $change_date < $end_date) {
            $checkpoints[] = $change_date;
        }
    }
    $checkpoints[] = $end_date;

    // Remove duplicates and sort dates
    $unique_checkpoints = [];
    foreach ($checkpoints as $date) {
        $unique_checkpoints[$date->format('Y-m-d')] = $date;
    }
    sort($unique_checkpoints);

    for ($i = 0; $i < count($unique_checkpoints) - 1; $i++) {
        $period_start = $unique_checkpoints[$i];
        $period_end = $unique_checkpoints[$i + 1];
        $days = $period_end->diff($period_start)->days;

        if ($days <= 0) continue;

        // Determine rates for this sub-period
        // Find the latest rate change that happened on or before period_start
        $current_rates = $rate_history[0]; // Default to initial
        foreach ($rate_history as $change) {
            if ($period_start->format('Y-m-d') >= $change['start_date']) {
                $current_rates = $change;
            }
        }

        $normal_rate = $current_rates['interest_rate'];
        $overdue_rate = $current_rates['overdue_rate'];

        $is_overdue = $period_start >= $due_date;

        // 윤년 고려하여 일할 계산
        $temp_date = clone $period_start;
        for ($d = 0; $d < $days; $d++) {
            $days_in_year = is_leap_year((int)$temp_date->format('Y')) ? 366 : 365;
            $daily_normal_rate = $normal_rate / 100 / $days_in_year;
            $normal_interest += $principal * $daily_normal_rate;

            if ($is_overdue) {
                $daily_penalty_rate = ($overdue_rate - $normal_rate) / 100 / $days_in_year;
                $overdue_interest += $principal * $daily_penalty_rate;
            }
            $temp_date->modify('+1 day');
        }
    }

    $final_normal = floor($normal_interest);
    $final_overdue = floor($overdue_interest);
    $total_interest = $final_normal + $final_overdue;

    return ['normal' => $final_normal, 'overdue' => $final_overdue, 'total' => $total_interest, 'details' => $details];
}


function calculateAccruedInterest($link, $contract, $target_date_str)
{
    $contract_id = $contract['id'];
    $loan_date_str = $contract['loan_date'];

    // Use the last interest calculation date from the contract if available.
    // This date is updated by recalculate_and_update_contract_state.
    if (!empty($contract['last_interest_calc_date'])) {
        $last_interest_payment_date_str = $contract['last_interest_calc_date'];
    } else {
        // Fallback to the last collection date if the specific interest calc date isn't set.
        $last_payment_query = mysqli_prepare($link, "SELECT MAX(collection_date) as last_date FROM collections WHERE contract_id = ? AND deleted_at IS NULL");
        mysqli_stmt_bind_param($last_payment_query, "i", $contract_id);
        mysqli_stmt_execute($last_payment_query);
        $last_interest_payment_date_str = mysqli_fetch_assoc(mysqli_stmt_get_result($last_payment_query))['last_date'] ?? $loan_date_str;
        mysqli_stmt_close($last_payment_query);
    }

    $outstanding_principal = (float)($contract['current_outstanding_principal'] ?? calculateOutstandingPrincipal($link, $contract_id));
    $current_due_date = $contract['next_due_date'] ?? $contract['loan_date'];

    return calculateAccruedInterestForPeriod($link, $contract, $outstanding_principal, $last_interest_payment_date_str, $target_date_str, $current_due_date);
}

/**
 * Processes a new collection, saves it to the database, and updates the contract state.
 * This function handles the entire logic within a single database transaction.
 *
 * @param mysqli $link The database connection.
 * @param int $contract_id The ID of the contract.
 * @param string $collection_date_str The date of the collection.
 * @param float $total_amount The total amount collected.
 * @param float $expense_payment The portion of the total amount allocated to expenses.
 * @param float $interest_payment The portion of the total amount allocated to interest.
 * @param float $principal_payment The portion of the total amount allocated to principal.
 * @param string $memo The general memo for the collection.
 * @param string $expense_memo The specific memo for the expense.
 * @return bool True on success, false on failure.
 * @throws Exception On validation or database errors.
 */
function process_collection($link, $contract_id, $collection_date_str, $total_amount, $expense_payment, $interest_payment, $principal_payment, $memo, $expense_memo, $transaction_id)
{
    // 1. Data Validation
    $stmt_dates = mysqli_prepare($link, "SELECT loan_date, (SELECT MAX(collection_date) FROM collections WHERE contract_id = ? AND deleted_at IS NULL) as last_collection FROM contracts WHERE id = ?");
    mysqli_stmt_bind_param($stmt_dates, "ii", $contract_id, $contract_id);
    mysqli_stmt_execute($stmt_dates);
    $dates = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dates));
    mysqli_stmt_close($stmt_dates);

    $min_allowed_date_str = $dates['last_collection'] ?? $dates['loan_date'];
    if (new DateTime($collection_date_str) <= new DateTime($min_allowed_date_str)) {
        throw new Exception("입금일은 마지막 거래일({$min_allowed_date_str}) 이후여야 합니다.");
    }

    if ($total_amount <= 0) {
        throw new Exception("입금액은 0보다 커야 합니다.");
    }

    // 2. Save Collection Records
    $base_memo = "[자동분개] " . $memo;

    // Calculate accrued interest for generated_interest field
    // Calculate accrued interest for generated_interest field
    $contract_data = getContractById($link, $contract_id);

    // Use stored values instead of Full Replay (get_current_contract_state)
    $existing_shortfall = (float)$contract_data['shortfall_amount'];
    $outstanding_principal = (float)$contract_data['current_outstanding_principal'];

    $interest_data = calculateAccruedInterest($link, $contract_data, $collection_date_str);
    $total_interest_to_be_paid = $interest_data['total'] + $existing_shortfall;

    // Security/Validation: Ensure principal payment does not exceed the outstanding principal.
    if ($principal_payment > $outstanding_principal) {
        throw new Exception("원금 상환액(" . number_format($principal_payment) . "원)이 대출 잔액(" . number_format($outstanding_principal) . "원)을 초과할 수 없습니다.");
    }

    // Insert Expense
    if ($expense_payment > 0) {
        $sql_expense = "INSERT INTO collections (transaction_id, contract_id, collection_date, collection_type, amount, memo) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_expense = mysqli_prepare($link, $sql_expense);
        $type_expense = '경비';
        $final_expense_memo = $base_memo . ($expense_memo ? " (경비: " . $expense_memo . ")" : "");
        mysqli_stmt_bind_param($stmt_expense, "sissds", $transaction_id, $contract_id, $collection_date_str, $type_expense, $expense_payment, $final_expense_memo);
        if (!mysqli_stmt_execute($stmt_expense)) throw new Exception("경비 내역 저장 실패: " . mysqli_stmt_error($stmt_expense));

        // [NEW] Get the ID of the inserted collection record
        $linked_collection_id = mysqli_insert_id($link);
        mysqli_stmt_close($stmt_expense);

        // [NEW] Process contract_expenses
        // 수납된 경비 금액만큼 미처리 비용을 오래된 순서대로 '처리됨'으로 변경합니다.
        $remaining_expense_payment = $expense_payment;
        error_log("Processing expenses for contract $contract_id. Payment: $expense_payment");

        $stmt_expenses = mysqli_prepare($link, "SELECT id, amount FROM contract_expenses WHERE contract_id = ? AND is_processed = 0 ORDER BY expense_date ASC, id ASC");
        mysqli_stmt_bind_param($stmt_expenses, "i", $contract_id);
        mysqli_stmt_execute($stmt_expenses);
        $result_expenses = mysqli_stmt_get_result($stmt_expenses);

        while ($exp = mysqli_fetch_assoc($result_expenses)) {
            error_log("Found expense ID: {$exp['id']}, Amount: {$exp['amount']}");
            if ($remaining_expense_payment >= $exp['amount']) {
                // Mark as processed AND link to collection ID
                $stmt_upd = mysqli_prepare($link, "UPDATE contract_expenses SET is_processed = 1, processed_date = NOW(), linked_collection_id = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_upd, "ii", $linked_collection_id, $exp['id']);
                mysqli_stmt_execute($stmt_upd);
                mysqli_stmt_close($stmt_upd);

                $remaining_expense_payment -= $exp['amount'];
                error_log("Marked expense {$exp['id']} as processed. Remaining: $remaining_expense_payment");
            } else {
                // Partial payment - 잔액이 부족하면 처리를 중단합니다. (완전 납부된 건만 처리)
                error_log("Insufficient remaining payment for expense {$exp['id']}. Stopping.");
                break;
            }
        }
        mysqli_stmt_close($stmt_expenses);
    }

    // Insert Interest
    if ($interest_payment > 0) {
        $sql_interest = "INSERT INTO collections (transaction_id, contract_id, collection_date, collection_type, amount, memo, generated_interest) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_interest = mysqli_prepare($link, $sql_interest);
        $type_interest = '이자';
        mysqli_stmt_bind_param($stmt_interest, "sissdsd", $transaction_id, $contract_id, $collection_date_str, $type_interest, $interest_payment, $base_memo, $total_interest_to_be_paid);
        if (!mysqli_stmt_execute($stmt_interest)) throw new Exception("이자 내역 저장 실패: " . mysqli_stmt_error($stmt_interest));
        mysqli_stmt_close($stmt_interest);
    }

    // Insert Principal
    if ($principal_payment > 0) {
        $sql_principal = "INSERT INTO collections (transaction_id, contract_id, collection_date, collection_type, amount, memo) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_principal = mysqli_prepare($link, $sql_principal);
        $type_principal = '원금';
        mysqli_stmt_bind_param($stmt_principal, "sissds", $transaction_id, $contract_id, $collection_date_str, $type_principal, $principal_payment, $base_memo);
        if (!mysqli_stmt_execute($stmt_principal)) throw new Exception("원금 내역 저장 실패: " . mysqli_stmt_error($stmt_principal));
        mysqli_stmt_close($stmt_principal);
    }

    // 3. Update Contract State
    return recalculate_and_update_contract_state($link, $contract_id, false, $contract_data); // Pass preloaded data
}

/**
 * Creates a backup of the current contract state before a new collection is processed.
 *
 * @param mysqli $link The database connection.
 * @param int $contract_id The ID of the contract.
 * @param string $transaction_id The transaction ID of the upcoming collection.
 * @return bool True on success, false on failure.
 */
function create_contract_state_backup($link, $contract_id, $transaction_id)
{
    $sql_backup = "INSERT INTO contract_state_backups 
                        (contract_id, collection_transaction_id, status, current_outstanding_principal, shortfall_amount, next_due_date, last_interest_calc_date)
                   SELECT 
                        id, ?, status, current_outstanding_principal, shortfall_amount, next_due_date, last_interest_calc_date
                   FROM contracts 
                   WHERE id = ?";

    if ($stmt = mysqli_prepare($link, $sql_backup)) {
        mysqli_stmt_bind_param($stmt, "si", $transaction_id, $contract_id);
        return mysqli_stmt_execute($stmt);
    }
    return false;
}

/**
 * Calculates the current state of a contract by replaying all its transactions.
 * This is the single source of truth for principal and shortfall.
 *
 * @param mysqli $link The database connection.
 * @param array $contract The contract data array.
 * @return array An array containing 'outstanding_principal', 'current_shortfall', and 'last_interest_calc_date'.
 */
function get_current_contract_state($link, $contract)
{
    $contract_id = $contract['id'];
    $collections_for_calc = getCollections($link, '', '', '', $contract_id, false);

    $ledger_entries = [['date' => $contract['loan_date'], 'type' => 'loan', 'amount' => (float)$contract['loan_amount']]];
    $grouped_collections = [];
    foreach ($collections_for_calc as $c) {
        $key = $c['transaction_id'] ?? 'manual_' . $c['id'];
        if (!isset($grouped_collections[$key])) {
            $grouped_collections[$key] = ['date' => $c['collection_date'], 'type' => 'payment', 'total_paid' => 0, 'expense' => 0];
        }
        $amount = (float)$c['amount'];
        $grouped_collections[$key]['total_paid'] += $amount;
        if ($c['collection_type'] == '경비') {
            $grouped_collections[$key]['expense'] += $amount;
        }
    }
    foreach ($grouped_collections as $gc) $ledger_entries[] = $gc;
    // Sort by date first, then by ID for manual entries to keep their original order on the same day
    usort($ledger_entries, function ($a, $b) {
        return strcmp($a['date'], $b['date']) ?: ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    });

    $running_balance = 0;
    $current_shortfall = (float)($contract['initial_shortfall'] ?? 0);
    $last_interest_calc_date = $contract['loan_date'];

    foreach ($ledger_entries as $entry) {
        if ($entry['date'] > $last_interest_calc_date) {
            $interest_data_period = calculateAccruedInterestForPeriod($link, $contract, $running_balance, $last_interest_calc_date, $entry['date'], $contract['next_due_date'] ?? $entry['date']);
            $current_shortfall += $interest_data_period['total'];
        }

        if ($entry['type'] === 'loan') {
            $running_balance += $entry['amount'];
        } else if ($entry['type'] === 'payment') {
            $payment_for_interest = min($entry['total_paid'] - $entry['expense'], max(0, $current_shortfall));
            $current_shortfall -= $payment_for_interest;
            $payment_for_principal = min(max(0, $entry['total_paid'] - $entry['expense'] - $payment_for_interest), $running_balance);
            $running_balance -= $payment_for_principal;
        }
        $last_interest_calc_date = $entry['date'];
    }

    return ['outstanding_principal' => $running_balance, 'current_shortfall' => $current_shortfall, 'last_interest_calc_date' => $last_interest_calc_date];
}

function get_next_due_date($base_date, $agreement_day, $exceptions, $shortfall = 0, $previous_due_date = null)
{
    $next_due = clone $base_date;

    // 1. Check for "Early Payment" for the NEXT month first.
    // Calculate the agreement date for the NEXT month relative to the base date
    $next_month_date = clone $base_date;
    $next_month_date->modify('first day of next month');
    $next_month_agreement_date = clone $next_month_date;
    $next_month_agreement_date->setDate(
        (int)$next_month_date->format('Y'),
        (int)$next_month_date->format('m'),
        min($agreement_day, (int)$next_month_date->format('t'))
    );

    $next_month_grace_start = clone $next_month_agreement_date;
    $next_month_grace_start->modify('-10 days');

    // If payment is within the window for the NEXT month, jump to the month AFTER next.
    if ($shortfall < 10000 && $base_date >= $next_month_grace_start) {
        $next_due->modify('first day of next month'); // Move to next month
        $next_due->modify('first day of next month'); // Move to month after next
    } else {
        // 2. Standard Logic (Current Month)
        $current_month_agreement_date = clone $next_due;
        $current_month_agreement_date->setDate(
            (int)$next_due->format('Y'),
            (int)$next_due->format('m'),
            min($agreement_day, (int)$next_due->format('t'))
        );

        $grace_period_start = clone $current_month_agreement_date;
        $grace_period_start->modify('-10 days');

        if ($shortfall < 10000) {
            if ($base_date >= $grace_period_start) {
                $next_due->modify('first day of next month');
            }
        }
    }

    $year = (int)$next_due->format('Y');
    $month = (int)$next_due->format('m');
    $day_to_set = min($agreement_day, (int)date('t', mktime(0, 0, 0, $month, 1, $year)));
    $next_due->setDate($year, $month, $day_to_set);

    // Skip holidays (using isHoliday logic)
    while (isHoliday($next_due->format('Y-m-d'), $exceptions)) {
        $next_due->modify('+1 day');
    }

    // [NEW] If there is a significant shortfall, do not advance beyond the previous due date.
    if ($shortfall >= 10000 && $previous_due_date !== null) {
        // Ensure previous_due_date is a DateTime object
        if (!$previous_due_date instanceof DateTime) {
            $previous_due_date = new DateTime($previous_due_date);
        }

        if ($next_due > $previous_due_date) {
            return $previous_due_date;
        }
    }

    return $next_due;
}

function recalculate_and_update_contract_state($link, $contract_id, $is_manual_upload = false, $preloaded_contract = null)
{
    mysqli_begin_transaction($link);

    try {
        $contract = $preloaded_contract;
        if ($contract === null) { // Fetch only if not preloaded
            $stmt_contract = mysqli_prepare($link, "SELECT * FROM contracts WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt_contract, "i", $contract_id);
            mysqli_stmt_execute($stmt_contract);
            $contract = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_contract));
            mysqli_stmt_close($stmt_contract);
        }

        if (!$contract) {
            throw new Exception("계약 정보를 찾을 수 없습니다.");
        }

        // --- 개선된 로직 ---
        // 1. DB에 저장된 현재 계약 상태를 기준으로 시작합니다.
        $running_balance = (float)$contract['current_outstanding_principal'];
        $cumulative_shortfall = (float)$contract['shortfall_amount'];
        $last_calc_date_str = $contract['last_interest_calc_date'] ?? $contract['loan_date'];

        // 2. 마지막 계산일 이후의 모든 입금 내역을 가져옵니다.
        $stmt_new_collections = mysqli_prepare($link, "SELECT * FROM collections WHERE contract_id = ? AND collection_date > ? AND deleted_at IS NULL ORDER BY collection_date ASC, id ASC");
        mysqli_stmt_bind_param($stmt_new_collections, "is", $contract_id, $last_calc_date_str);
        mysqli_stmt_execute($stmt_new_collections);
        $new_collections = mysqli_fetch_all(mysqli_stmt_get_result($stmt_new_collections), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_new_collections);

        // 3. 새로운 입금 내역들을 순서대로 적용하여 최종 상태를 계산합니다.
        $last_processed_date = new DateTime($last_calc_date_str);
        foreach ($new_collections as $collection) {
            $collection_date = new DateTime($collection['collection_date']);
            $interest_data = calculateAccruedInterestForPeriod($link, $contract, $running_balance, $last_processed_date->format('Y-m-d'), $collection_date->format('Y-m-d'), $contract['next_due_date']);
            $cumulative_shortfall += $interest_data['total'];

            if ($collection['collection_type'] === '이자') $cumulative_shortfall -= (float)$collection['amount'];
            if ($collection['collection_type'] === '원금') $running_balance -= (float)$collection['amount'];

            $last_processed_date = $collection_date;
        }

        $exceptions = getHolidayExceptions();
        $agreement_day = (int)$contract['agreement_date'];

        // For manual uploads, we trust the shortfall amount already set on the contract.
        // For regular operations, we use the recalculated shortfall.
        $final_shortfall = $is_manual_upload ? (float)$contract['shortfall_amount'] : ($cumulative_shortfall > 0 ? floor($cumulative_shortfall) : 0);

        // 항상 마지막 거래일을 기준으로 다음 약정일을 새로 계산합니다.
        // [NEW] Pass the previous due date (from contract state before these new collections) to prevent skipping if overdue.
        $previous_due_date_for_calc = $contract['next_due_date'];
        $final_next_due_date = get_next_due_date($last_processed_date, $agreement_day, $exceptions, $final_shortfall, $previous_due_date_for_calc);

        $new_status = 'active';
        $today = new DateTime(date('Y-m-d')); // Use date only to prevent time-based issues
        $today->setTime(0, 0, 0);
        if ($running_balance <= 0.01 && $final_shortfall <= 100) { // Allow small remainder
            $new_status = 'paid';
            $final_next_due_date_str = null;
        } else if ($today > $final_next_due_date) {
            $new_status = 'overdue';
        }
        $final_next_due_date_str = ($new_status === 'paid') ? null : $final_next_due_date->format('Y-m-d');

        $last_interest_calc_date_str = $last_processed_date->format('Y-m-d');
        $stmt_update = mysqli_prepare($link, "UPDATE contracts SET status = ?, next_due_date = ?, shortfall_amount = ?, last_interest_calc_date = ?, current_outstanding_principal = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_update, "ssdsdi", $new_status, $final_next_due_date_str, $final_shortfall, $last_interest_calc_date_str, $running_balance, $contract_id);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        mysqli_commit($link);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($link);
        error_log("State recalculation failed for contract $contract_id: " . $e->getMessage());
        return false;
    }
}

function get_all_company_info($link)
{
    $info = [];
    try {
        $sql = "SELECT info_key, info_value FROM company_info";
        $result = mysqli_query($link, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $info[$row['info_key']] = $row['info_value'];
            }
        }
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist, do nothing and proceed with defaults.
    }
    // Provide defaults if table is empty or doesn't exist
    $defaults = [
        'company_name' => '(주)페이데이캐피탈대부',
        'ceo_name' => '홍길동',
        'company_address' => '서울특별시 강남구 테헤란로 123, 45층',
        'company_phone' => '1666-6979',
        'manager_name' => '',
        'manager_phone' => '',
        'slack_notifications_enabled' => '1', // 기본값: 사용
        'wideshot_api_key' => '', // API 키 기본값
        'default_sender_phone' => '1666-6979' // 운영용 기본 발신번호
    ];
    return array_merge($defaults, $info);
}

function update_company_info($link, $key, $value)
{
    $sql = "INSERT INTO company_info (info_key, info_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE info_value = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "sss", $key, $value, $value);
        return mysqli_stmt_execute($stmt);
    }
    return false;
}

function get_certificate_template_path($template_key)
{
    return __DIR__ . '/templates/certificates/' . $template_key . '.html';
}

function get_certificate_template($template_key)
{
    $file_path = get_certificate_template_path($template_key);
    if (file_exists($file_path)) {
        return file_get_contents($file_path);
    }
    return false;
}

// --- Classification Code Helper Functions ---

/**
 * Fetches all classification codes.
 * @param mysqli $link
 * @return array
 */
function get_all_classification_codes($link)
{
    $sql = "SELECT * FROM classification_codes ORDER BY code ASC";
    $result = mysqli_query($link, $sql);
    if ($result) {
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    return [];
}

/**
 * Fetches classification codes assigned to a specific contract.
 * @param mysqli $link
 * @param int $contract_id
 * @return array
 */
function get_contract_classifications($link, $contract_id)
{
    $sql = "SELECT cc.* 
            FROM classification_codes cc
            JOIN contract_classifications ccl ON cc.id = ccl.classification_code_id
            WHERE ccl.contract_id = ?
            ORDER BY cc.code ASC";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $contract_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Assigns a classification code to a contract.
 * @param mysqli $link
 * @param int $contract_id
 * @param int $code_id
 * @return bool
 */
function add_contract_classification($link, $contract_id, $code_id)
{
    $sql = "INSERT IGNORE INTO contract_classifications (contract_id, classification_code_id) VALUES (?, ?)";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $contract_id, $code_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Removes a classification code from a contract.
 * @param mysqli $link
 * @param int $contract_id
 * @param int $code_id
 * @return bool
 */
function remove_contract_classification($link, $contract_id, $code_id)
{
    $sql = "DELETE FROM contract_classifications WHERE contract_id = ? AND classification_code_id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $contract_id, $code_id);
    return mysqli_stmt_execute($stmt);
}
function get_all_certificate_templates()
{
    $template_dir = __DIR__ . '/templates/certificates/';
    if (!is_dir($template_dir)) {
        mkdir($template_dir, 0755, true);
    }

    $templates = [];
    $default_titles = get_default_certificate_titles();

    foreach (array_keys($default_titles) as $key) {
        $file_path = get_certificate_template_path($key);
        $content = file_exists($file_path) ? file_get_contents($file_path) : '';
        $templates[] = [
            'template_key' => $key,
            'title' => $default_titles[$key],
            'content' => $content,
        ];
    }
    return $templates;
}

function update_certificate_template($template_key, $content)
{
    $file_path = get_certificate_template_path($template_key);
    return file_put_contents($file_path, $content) !== false;
}

function get_default_certificate_titles()
{
    return [
        'payment_completion' => '완납증명서',
        'auction_notice' => '경매예정통보',
        'benefit_loss_notice' => '기한의 이익상실 통지서',
        'dunning_letter' => '독촉장',
        'legal_action_notice' => '법적절차 착수 최고 통보장',
        'claim_assignment_notice' => '채권 양도 통지서',
        'debt_repayment_cert' => '채무변제확인서',
        'transaction_history' => '거래내역서',
        'deposit_receipt' => '입금영수증',
        'repayment_schedule' => '상환예정 스케줄표',
        'debt_confirmation' => '채무확인서',
    ];
}

function initialize_certificate_templates()
{
    // 기본 템플릿 내용 정의
    $default_contents = [
        'debt_confirmation' => "<h1 style='text-align:center; font-size:24px;'>채 무 확 인 서</h1><br><br><p><strong>채무자 성명:</strong> [고객명]</p><p><strong>채무자 주소:</strong> [고객주소]</p><br><hr><br><p><strong>채권자</strong></p><p><strong>상호:</strong> [회사명]</p><p><strong>대표:</strong> [회사대표]</p><p><strong>주소:</strong> [회사주소]</p><p><strong>연락처:</strong> [회사연락처]</p><br><br><p>위 채무자는 [오늘날짜] 현재 당사에 대하여 아래와 같이 채무가 있음을 확인합니다.</p><br><p style='text-align:center;'><strong>- 아 래 -</strong></p><br><table style='width: 100%; border-collapse: collapse; text-align: left;' border='1'><tr><td style='padding: 8px; background-color: #f2f2f2; width: 30%;'><strong>계약번호</strong></td><td style='padding: 8px;'>[계약번호]</td></tr><tr><td style='padding: 8px; background-color: #f2f2f2;'><strong>대출일자</strong></td><td style='padding: 8px;'>[대출일]</td></tr><tr><td style='padding: 8px; background-color: #f2f2f2;'><strong>대출원금</strong></td><td style='padding: 8px;'>[대출원금]</td></tr><tr><td style='padding: 8px; background-color: #f2f2f2;'><strong>대출잔액</strong></td><td style='padding: 8px; font-weight: bold;'>[대출잔액]</td></tr></table><br><br><br><p style='text-align:center;'>[오늘날짜]</p><br><br><div style='text-align:center;'><p style='font-size:18px; font-weight:bold;'>[회사명]</p><p><strong>대표이사: [회사대표] [법인인감]</strong></p></div>",
        'repayment_schedule' => "<h1 style='text-align:center; font-size:24px;'>상환 예정 스케줄표</h1><br><p><strong>고객명:</strong> [고객명]</p><p><strong>계약번호:</strong> [계약번호]</p><p><strong>대출잔액:</strong> [대출잔액]</p><p><strong>약정일:</strong> 매월 [약정일]일</p><br>[상환스케줄테이블]<br><p>* 본 스케줄은 예상치이며, 실제 상환 시점에 따라 이자 금액이 변동될 수 있습니다.</p><br><br><div style='text-align:center;'><p style='font-size:18px; font-weight:bold;'>[회사명]</p></div>",
        'transaction_history' => "<h1 style='text-align:center; font-size:24px;'>거 래 내 역 서</h1><br><p><strong>고객명:</strong> [고객명]</p><p><strong>계약번호:</strong> [계약번호]</p><p><strong>대출원금:</strong> [대출원금]</p><p><strong>대출일:</strong> [대출일]</p><p><strong>조회기간:</strong> [조회시작일] ~ [조회종료일]</p><br>[거래내역테이블]<br><br><p>위와 같이 거래내역을 확인합니다.</p><br><br><div style='text-align:center;'><p style='font-size:18px; font-weight:bold;'>[회사명]</p></div>",
    ];

    $titles = get_default_certificate_titles();

    $default_template_dir = __DIR__ . '/templates/certificates/defaults/';
    if (!is_dir($default_template_dir)) {
        mkdir($default_template_dir, 0755, true);
    }

    foreach ($titles as $key => $title) {
        $default_file_path = $default_template_dir . $key . '.html';

        if (file_exists($default_file_path)) {
            $content = file_get_contents($default_file_path);
        } else {
            // 파일이 없으면, 정의된 기본 내용 또는 기본 문구를 사용하여 파일을 생성합니다.
            $content = $default_contents[$key] ?? "<p>'{$title}' 템플릿의 기본 내용이 없습니다. 관리자 페이지에서 내용을 수정해주세요.</p>";
            file_put_contents($default_file_path, $content);
        }

        // 실제 사용될 템플릿 파일이 없으면 기본 파일로 생성합니다.
        if (update_certificate_template($key, $content) === false) {
            return false;
        }
    }
    return true;
}

function get_transaction_ledger_data($link, $contract_id, $start_date = null, $end_date = null)
{
    $contract = getContractById($link, $contract_id);
    if (!$contract) return [];

    $collections = getCollections($link, '', '', '', $contract_id);

    $ledger_entries = [];
    $ledger_entries[] = [
        'date' => $contract['loan_date'],
        'type' => 'loan',
        'amount' => (float)$contract['loan_amount'],
    ];

    $grouped_collections = [];
    foreach ($collections as $collection) {
        $key = $collection['transaction_id'] ?? 'manual_' . $collection['id'];
        if (!isset($grouped_collections[$key])) {
            $grouped_collections[$key] = [
                'date' => $collection['collection_date'],
                'type' => 'payment',
                'total_paid' => 0,
                'interest' => 0,
                'principal' => 0,
                'expense' => 0,
            ];
        }
        $amount = (float)$collection['amount'];
        $grouped_collections[$key]['total_paid'] += $amount;
        if ($collection['collection_type'] == '이자') $grouped_collections[$key]['interest'] += $amount;
        elseif ($collection['collection_type'] == '원금') $grouped_collections[$key]['principal'] += $amount;
        elseif ($collection['collection_type'] == '경비') $grouped_collections[$key]['expense'] += $amount;
    }

    foreach ($grouped_collections as $data) {
        $ledger_entries[] = $data;
    }

    usort($ledger_entries, fn($a, $b) => strcmp($a['date'], $b['date']));

    $processed_ledger = [];
    $running_balance = 0;

    // Calculate starting balance if start_date is provided
    if ($start_date && $start_date > $contract['loan_date']) {
        $starting_balance = (float)$contract['loan_amount'];
        $principal_paid_before_start = 0;

        $stmt = mysqli_prepare($link, "SELECT SUM(amount) FROM collections WHERE contract_id = ? AND collection_type = '원금' AND collection_date < ? AND deleted_at IS NULL");
        mysqli_stmt_bind_param($stmt, "is", $contract_id, $start_date);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $principal_paid_before_start);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        $running_balance = $starting_balance - (float)$principal_paid_before_start;

        $processed_ledger[] = [
            'date' => $start_date,
            'description' => '이월잔액',
            'debit' => 0,
            'credit' => 0,
            'balance' => $running_balance
        ];
    }

    foreach ($ledger_entries as $entry) {
        if ($entry['type'] === 'loan') {
            $running_balance += $entry['amount'];
            $processed_entry = [
                'date' => $entry['date'],
                'description' => '대출실행',
                'debit' => $entry['amount'],
                'credit' => 0,
                'balance' => $running_balance
            ];
            // If start_date is set, only add the loan entry if it's within the range
            if ($start_date && $entry['date'] < $start_date) {
                continue; // Skip, as its effect is in the brought-forward balance
            }
        } else if ($entry['type'] === 'payment') {
            $running_balance -= $entry['principal'];
            $processed_entry = [
                'date' => $entry['date'],
                'description' => '입금',
                'debit' => 0,
                'credit' => $entry['total_paid'],
                'balance' => $running_balance,
                'interest_paid' => $entry['interest'],
                'principal_paid' => $entry['principal'],
            ];
        }
        // Filter by date range
        if (($start_date && $entry['date'] < $start_date) || ($end_date && $entry['date'] > $end_date)) {
            continue;
        }
        $processed_ledger[] = $processed_entry;
    }

    return $processed_ledger;
}

/**
 * Retrieves a contract by its ID.
 * @param mysqli $link Database connection.
 * @param int $id Contract ID.
 * @return array|null Contract data or null if not found.
 */
function getContractById($link, $id)
{
    if (!$id) return null;
    $stmt = mysqli_prepare($link, "SELECT * FROM contracts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $contract = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $contract;
}
