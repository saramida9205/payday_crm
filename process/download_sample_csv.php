<?php
$type = $_GET['type'] ?? 'default';

if ($type === 'manual') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sample_manual_bulk_collections.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // Add BOM for Excel

    fputcsv($output, ['계약번호', '입금일자', '입금액', '이자상환금액', '부족금발생금액', '원금상환금액', '메모']);
    fputcsv($output, ['26', '2020-10-16', '300000', '257533', '257508', '42467', '과거 데이터']);

} else {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sample_bulk_collections.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // Add BOM for Excel
    fputcsv($output, ['계약번호', '입금일자(YYYY-MM-DD)', '총입금금액']);
    fputcsv($output, ['1', '2023-01-15', '150000']);
    fputcsv($output, ['2', '2023-01-16', '250000']);
}
?>