<?php
require_once __DIR__ . '/transaction_process.php';
require_once __DIR__ . '/../common.php';

// Get filter parameters from GET request
$dp_start_date = $_GET['dp_start_date'] ?? '';
$dp_end_date = $_GET['dp_end_date'] ?? '';
$dp_customer_name = $_GET['dp_customer_name'] ?? '';
$dp_sort_order = $_GET['dp_sort_order'] ?? 'desc';

// Fetch the filtered data
$deposit_details = getDepositDetails($link, $dp_start_date, $dp_end_date, $dp_customer_name, $dp_sort_order);

// Set headers for CSV download
$filename = "deposit_details_" . date('Ymd') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add BOM to fix UTF-8 in Excel
fputs($output, "\xEF\xBB\xBF");

// Column headings
$headings = [
    '회원번호', '계약번호', '고객명', '이전입금일', '현재입금일', '약정일',
    '입금액', '비용상환', '이자상환', '원금상환', '부족금액', '현재잔액'
];
fputcsv($output, $headings);

$totals = ['count' => 0, 'total_deposit' => 0, 'expense' => 0, 'interest' => 0, 'principal' => 0];

// Data rows
if (!empty($deposit_details)) {
    foreach ($deposit_details as $row) {
        $row_data = [
            $row['customer_id'], $row['contract_id'], $row['customer_name'],
            $row['previous_deposit_date'], $row['collection_date'], $row['agreement_date'] . '일',
            $row['total_deposit'], $row['expense'], $row['interest'], $row['principal'],
            $row['shortfall'], $row['balance_as_of']
        ];
        fputcsv($output, $row_data);

        // Sum totals
        $totals['count']++;
        $totals['total_deposit'] += $row['total_deposit'];
        $totals['expense'] += $row['expense'];
        $totals['interest'] += $row['interest'];
        $totals['principal'] += $row['principal'];
    }
}

// Add summary row
fputcsv($output, []); // Blank row
$summary_row = [
    '합계', '', '', '총 ' . $totals['count'] . ' 건', '', '',
    $totals['total_deposit'], $totals['expense'], $totals['interest'], $totals['principal'],
    '', ''
];
fputcsv($output, $summary_row);

fclose($output);
exit;
?>