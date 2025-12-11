<?php
require_once __DIR__ . '/transaction_process.php';
require_once __DIR__ . '/../common.php';

// Get filter parameters from GET request
$wd_start_date = $_GET['wd_start_date'] ?? '';
$wd_end_date = $_GET['wd_end_date'] ?? '';
$wd_customer_name = $_GET['wd_customer_name'] ?? '';
$wd_sort_order = $_GET['wd_sort_order'] ?? 'desc';

// Fetch the filtered data
$withdrawal_details = getWithdrawalDetails($link, $wd_start_date, $wd_end_date, $wd_customer_name, $wd_sort_order);

// Set headers for CSV download
$filename = "withdrawal_details_" . date('Ymd') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add BOM to fix UTF-8 in Excel
fputs($output, "\xEF\xBB\xBF");

// Column headings
$headings = [
    '회원번호', 
    '계약번호', 
    '현재상태',
    '상품명',
    '고객명', 
    '계약일(출금일)', 
    '만기일', 
    '약정일', 
    '차기상환일', 
    '이율(%)', 
    '출금금액', 
    '송금계좌', 
    '조회기준일 잔액'
];
fputcsv($output, $headings);

// Data rows
if (!empty($withdrawal_details)) {
    foreach ($withdrawal_details as $row) {
        $row_data = [
            $row['customer_id'], $row['contract_id'], strip_tags(get_status_display($row['status'])),
            $row['product_name'],
            $row['customer_name'], $row['loan_date'],
            $row['maturity_date'], $row['agreement_date'] . '일', $row['next_due_date'],
            $row['interest_rate'], $row['loan_amount'], $row['bank_name'] . ' ' . $row['account_number'],
            $row['balance_as_of']
        ];
        fputcsv($output, $row_data);
    }
}

fclose($output);
exit;
?>