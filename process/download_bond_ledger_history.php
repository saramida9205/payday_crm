<?php
require_once __DIR__ . '/../common.php';

global $link;

// Get search parameters from GET request
$selected_date = $_GET['snapshot_date'] ?? '';
$search_term = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : null;
$limit = isset($_GET['limit']) ? ($_GET['limit'] === 'all' ? 'all' : (int)$_GET['limit']) : null;

if (empty($selected_date)) {
    die("조회 기준일이 선택되지 않았습니다.");
}

// Build the SQL query based on search parameters (same as bond_ledger_history.php)
$sql = "SELECT * FROM bond_ledger_snapshots WHERE snapshot_date = ?";
$params = [$selected_date];
$types = 's';

if (!empty($search_term)) {
    $sql .= " AND (customer_name LIKE ? OR contract_id LIKE ?)";
    $search_like = '%' . $search_term . '%';
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'ss';
}

$sql .= " ORDER BY loan_date DESC";

$stmt = mysqli_prepare($link, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$all_snapshots = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Apply pagination if page and limit are provided
if ($page && $limit && $limit !== 'all') {
    $offset = ($page - 1) * $limit;
    $snapshots_to_download = array_slice($all_snapshots, $offset, $limit);
} else {
    $snapshots_to_download = $all_snapshots;
}

// Set headers for CSV download
$filename = "bond_ledger_history_" . date('Ymd', strtotime($selected_date)) . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add BOM for Excel to recognize UTF-8
fputs($output, "\xEF\xBB\xBF");

// Column headings
$headings = [
    '계약No', '성명', '상품명', '주민등록주소', '총대출', '계약일', '만기일', '약정일',
    '금리/연체금리', '핸드폰', '상환방법', '당시 잔액', '당시 연체일수', '당시 상태',
    '당시 다음상환일', '당시 최근납입일'
];
fputcsv($output, $headings);

// Data rows
if (!empty($snapshots_to_download)) {
    foreach ($snapshots_to_download as $snapshot) {
        $status_display = strip_tags(get_status_display($snapshot['status'] ?? ''));

        $row_data = [
            $snapshot['contract_id'] ?? '', $snapshot['customer_name'] ?? '', $snapshot['product_name'] ?? '',
            $snapshot['address_registered'] ?? '', $snapshot['loan_amount'] ?? '', $snapshot['loan_date'] ?? '',
            $snapshot['maturity_date'] ?? '', isset($snapshot['agreement_date']) ? $snapshot['agreement_date'] . '일' : '',
            ($snapshot['interest_rate'] ?? '') . ' / ' . ($snapshot['overdue_interest_rate'] ?? '') . '%',
            $snapshot['customer_phone'] ?? '', $snapshot['repayment_method'] ?? '', $snapshot['outstanding_principal'] ?? '',
            ($snapshot['overdue_days'] ?? 0) > 0 ? $snapshot['overdue_days'] . '일' : '',
            $status_display, $snapshot['next_due_date'] ?? '', $snapshot['last_interest_calc_date'] ?? ''
        ];

        fputcsv($output, $row_data);
    }
}

fclose($output);
exit;
?>