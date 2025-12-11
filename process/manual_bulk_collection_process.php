<?php
require_once __DIR__ . '/../common.php';

function parse_csv_with_encoding($filepath) {
    $csv_data = [];
    $file_content = file_get_contents($filepath);
    $encoding = mb_detect_encoding($file_content, ['UTF-8', 'EUC-KR', 'ASCII'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $file_content = mb_convert_encoding($file_content, 'UTF-8', $encoding);
    }
    if (substr($file_content, 0, 3) === "\xEF\xBB\xBF") {
        $file_content = substr($file_content, 3);
    }
    $file_content = str_replace(["\r\n", "\r"], "\n", $file_content);
    $lines = explode("\n", $file_content);

    if (count($lines) > 0) {
        $header_line = array_shift($lines);
        $header = str_getcsv($header_line);
        $header = array_map('trim', $header);
        
        if (empty($header) || !in_array('계약번호', $header)) {
             throw new Exception("CSV 헤더를 제대로 분석하지 못했습니다. 헤더: " . print_r($header, true));
        }
        foreach ($lines as $line_num => $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            if (count($header) == count($row)) {
                $csv_data[] = array_combine($header, $row);
            } else {
                throw new Exception(($line_num + 2) . "번째 행의 열 개수가 헤더와 일치하지 않습니다.");
            }
        }
    }
    return $csv_data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_manual_bulk_collections'])) {
    if (isset($_FILES['manual_bulk_upload_file']) && $_FILES['manual_bulk_upload_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['manual_bulk_upload_file']['tmp_name'];
        
        mysqli_begin_transaction($link);
        try {
            $csv_data = parse_csv_with_encoding($file_tmp_path);
            if (empty($csv_data)) {
                throw new Exception("CSV 파일이 비어 있거나 데이터 행의 형식이 올바르지 않습니다.");
            }

            // Sort all data by contract_id and then by date
            usort($csv_data, function($a, $b) {
                $contract_cmp = (int)$a['계약번호'] <=> (int)$b['계약번호'];
                if ($contract_cmp !== 0) return $contract_cmp;
                return strtotime($a['입금일자'] ?? '0') <=> strtotime($b['입금일자'] ?? '0');
            });

            // Group data by contract_id to process them together
            $collections_by_contract = [];
            foreach ($csv_data as $row) {
                $collections_by_contract[$row['계약번호']][] = $row;
            }

            $total_success_count = 0;
            $affected_contracts = [];

            $sql_insert = "INSERT INTO collections (transaction_id, contract_id, collection_date, collection_type, amount, memo, generated_interest) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($link, $sql_insert);
            if (!$stmt_insert) {
                throw new Exception("Insert statement preparation failed: " . mysqli_error($link));
            }

            foreach ($collections_by_contract as $contract_id => $rows) {
                $last_row_for_contract = end($rows);

                foreach ($rows as $row_num => $row) {
                    $collection_date = trim($row['입금일자'] ?? '');
                    $total_payment = (float)str_replace(',', '', trim($row['입금액'] ?? '0'));
                    $interest_payment = (float)str_replace(',', '', trim($row['이자상환금액'] ?? '0'));
                    $principal_payment = (float)str_replace(',', '', trim($row['원금상환금액'] ?? '0'));
                    $generated_interest = (float)str_replace(',', '', trim($row['부족금발생금액'] ?? '0'));
                    $memo = trim($row['메모'] ?? '');

                    // If interest and principal are both zero, but total payment is not,
                    // assume the total payment is for interest (as per user request for shortfall payment).
                    if ($interest_payment == 0 && $principal_payment == 0 && $total_payment > 0) {
                        $interest_payment = $total_payment;
                    } else {
                        // If the sum of breakdowns is less than the total payment,
                        // add the difference to the interest payment.
                        $breakdown_sum = $interest_payment + $principal_payment;
                        if ($total_payment > $breakdown_sum) {
                            $interest_payment += ($total_payment - $breakdown_sum);
                        }
                    }

                    if ($contract_id <= 0) throw new Exception("행 " . ($row_num + 2) . ": 유효하지 않은 계약번호입니다.");
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $collection_date)) throw new Exception("행 " . ($row_num + 2) . ": 유효하지 않은 입금일자 형식입니다 (YYYY-MM-DD).");

                    $transaction_id = uniqid('txn_manual_', true);
                    $base_memo = "[수기일괄입금] " . $memo;

                    if ($interest_payment > 0) {
                        $type = '이자';
                        mysqli_stmt_bind_param($stmt_insert, "sissdsd", $transaction_id, $contract_id, $collection_date, $type, $interest_payment, $base_memo, $generated_interest);
                        if (!mysqli_stmt_execute($stmt_insert)) throw new Exception("이자 내역 저장 실패: " . mysqli_stmt_error($stmt_insert));
                    }
                    if ($principal_payment > 0) {
                        $type = '원금';
                        $gen_interest_for_principal = 0; // Principal part has no generated interest
                        mysqli_stmt_bind_param($stmt_insert, "sissdsd", $transaction_id, $contract_id, $collection_date, $type, $principal_payment, $base_memo, $gen_interest_for_principal);
                        if (!mysqli_stmt_execute($stmt_insert)) throw new Exception("원금 내역 저장 실패: " . mysqli_stmt_error($stmt_insert));
                    }
                } // End inner loop

                // Update contract state once after processing all rows for this contract
                if (!recalculate_and_update_contract_state($link, $contract_id, true)) { // Pass true for manual upload
                    throw new Exception("계약 {$contract_id}의 최종 상태 업데이트(상태/다음납입일)에 실패했습니다.");
                }
                $affected_contracts[] = $contract_id;
            } // End outer loop

            mysqli_commit($link);
            $_SESSION['message'] = "총 " . count($csv_data) . "건의 수기계산 입금 내역이 성공적으로 업로드되었으며, " . count($affected_contracts) . "개 계약의 상태가 업데이트되었습니다.";

        } catch (Exception $e) {
            mysqli_rollback($link);
            $_SESSION['error_message'] = "일괄 입금 처리 중 오류 발생: " . $e->getMessage();
        }

    } else {
        $_SESSION['error_message'] = "파일 업로드 중 오류가 발생했습니다: " . ($_FILES['manual_bulk_upload_file']['error'] ?? '알 수 없는 오류');
    }
    header("location: ../pages/collection_manage.php");
    exit();
}

header("location: ../pages/collection_manage.php");
exit();
?>