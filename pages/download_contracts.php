<?php
require_once __DIR__ . '/../process/contract_process.php';

if (empty($_GET['contract_ids']) || !is_array($_GET['contract_ids'])) {
    die("다운로드할 계약을 선택해주세요.");
}

$contract_ids = $_GET['contract_ids'];
$placeholders = implode(',', array_fill(0, count($contract_ids), '?'));
$types = str_repeat('i', count($contract_ids));

$sql = "SELECT c.*, cu.name as customer_name FROM contracts c JOIN customers cu ON c.customer_id = cu.id WHERE c.id IN ($placeholders)";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$contract_ids);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$contracts = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=euc-kr');
header('Content-Disposition: attachment; filename=contracts_export_' . date('Ymd') . '.csv');

$output = fopen('php://output', 'w');

// Column headings
$headings = [
    '계약번호', '고객번호', '고객명', '상태', '상품명', '약정일', '대출금리', '연체금리', '상환방식', '대출일', '만기일', '대출원금', '대출잔액', '최근거래일', '다음약정일'
];
fputcsv($output, array_map(function($str) { return mb_convert_encoding($str, 'EUC-KR', 'UTF-8'); }, $headings));

// Data rows
if (!empty($contracts)) {
    foreach ($contracts as $contract) {
        $outstanding_principal = calculateOutstandingPrincipal($link, $contract['id'], $contract['loan_amount']);
        $row_data = [
            $contract['id'],
            $contract['customer_id'],
            $contract['customer_name'],
            strip_tags(get_status_display($contract['status'])),
            $contract['product_name'],
            $contract['agreement_date'],
            $contract['interest_rate'] . '%',
            $contract['overdue_interest_rate'] . '%',
            $contract['repayment_method'],
            $contract['loan_date'],
            $contract['maturity_date'],
            $contract['loan_amount'],
            $outstanding_principal,
            $contract['last_collection_date'] ?? '-',
            $contract['next_due_date'] ?? '-'
        ];
        fputcsv($output, array_map(function($str) { return mb_convert_encoding($str, 'EUC-KR', 'UTF-8'); }, $row_data));
    }
}

fclose($output);
exit;
?>