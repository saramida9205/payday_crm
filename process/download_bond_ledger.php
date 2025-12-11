<?php
require_once __DIR__ . '/../common.php';

global $link;

// Get search parameters from GET request
$search_params = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? 'valid',
    'page' => isset($_GET['page']) ? (int)$_GET['page'] : null,
    'limit' => isset($_GET['limit']) ? ($_GET['limit'] === 'all' ? 'all' : (int)$_GET['limit']) : null,
];

// Build the SQL query based on search parameters (same as bond_ledger.php)
$sql = "SELECT 
            c.id as contract_id, c.customer_id, c.product_name, c.loan_amount, c.loan_date, 
            c.maturity_date, c.agreement_date, c.interest_rate, c.overdue_interest_rate, 
            c.repayment_method, c.status, c.shortfall_amount, c.next_due_date, c.last_interest_calc_date,
            cu.name as customer_name, cu.resident_id_partial, cu.phone, cu.address_registered
        FROM contracts c
        JOIN customers cu ON c.customer_id = cu.id";

$where_clauses = [];
$params = [];
$types = '';

if ($search_params['status'] == 'valid') {
    $where_clauses[] = "c.status IN ('active', 'overdue')";
} elseif (!empty($search_params['status'])) {
    $where_clauses[] = "c.status = ?";
    $params[] = $search_params['status'];
    $types .= 's';
}

if (!empty($search_params['search'])) {
    $where_clauses[] = "(cu.name LIKE ? OR c.id LIKE ?)";
    $search_like = '%' . $search_params['search'] . '%';
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'ss';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY c.loan_date DESC, c.id DESC";

$stmt = mysqli_prepare($link, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$all_contracts = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Apply pagination if page and limit are provided
if ($search_params['page'] && $search_params['limit'] && $search_params['limit'] !== 'all') {
    $offset = ($search_params['page'] - 1) * $search_params['limit'];
    $contracts_to_download = array_slice($all_contracts, $offset, $search_params['limit']);
} else {
    $contracts_to_download = $all_contracts;
}

// Set headers for CSV download
$filename = "bond_ledger_" . date('Ymd') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add BOM for Excel to recognize UTF-8
fputs($output, "\xEF\xBB\xBF");

// Column headings
$headings = [
    '계약No', '성명', '상품명', '주민등록주소', '총대출', '계약일', '만기일', '약정일',
    '금리/연체금리', '핸드폰', '상환방법', '현재 잔액', '현재 연체일수', '현재 상태',
    '다음 상환일', '최근납입일' , '수기입력담보중'
];
fputcsv($output, $headings);

// Data rows
$today = new DateTime();
$today->setTime(0, 0, 0);

if (!empty($contracts_to_download)) {
    foreach ($contracts_to_download as $contract) {
        // Calculate dynamic values
        $outstanding_principal = isset($contract['contract_id']) && isset($contract['loan_amount']) ? calculateOutstandingPrincipal($link, $contract['contract_id'], $contract['loan_amount']) : 0;
        
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

        $status_display = strip_tags(get_status_display($contract['status'] ?? ''));

        $row_data = [
            $contract['contract_id'] ?? '',
            $contract['customer_name'] ?? '',
            $contract['product_name'] ?? '',
            $contract['address_registered'] ?? '',
            $contract['loan_amount'] ?? '',
            $contract['loan_date'] ?? '',
            $contract['maturity_date'] ?? '',
            isset($contract['agreement_date']) ? $contract['agreement_date'] . '일' : '',
            (isset($contract['interest_rate']) && isset($contract['overdue_interest_rate'])) ? $contract['interest_rate'] . ' / ' . $contract['overdue_interest_rate'] . '%' : '',
            $contract['phone'] ?? '',
            $repayment_method,
            $outstanding_principal,
            $overdue_days > 0 ? $overdue_days . '일' : '',
            $status_display,
            $contract['next_due_date'] ?? '',
            $contract['last_interest_calc_date'] ?? ''
        ];

        fputcsv($output, $row_data);
    }
}

fclose($output);
exit;
?>