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
        foreach ($header as $i => $title) {
            if ($title === '입금일자(YYYY-MM-DD)') {
                $header[$i] = '입금일자';
            }
        }
        if (empty($header) || !in_array('계약번호', $header)) {
             throw new Exception("CSV 헤더를 제대로 분석하지 못했습니다. 헤더: " . print_r($header, true));
        }
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            if (count($header) == count($row)) {
                $csv_data[] = array_combine($header, $row);
            }
        }
    }
    return $csv_data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_bulk_collections'])) {
    if (isset($_FILES['bulk_upload_file']) && $_FILES['bulk_upload_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['bulk_upload_file']['tmp_name'];
        
        mysqli_begin_transaction($link);
        try {
            $csv_data = parse_csv_with_encoding($file_tmp_path);
            if (empty($csv_data)) {
                throw new Exception("CSV 파일이 비어 있거나 데이터 행의 형식이 올바르지 않습니다.");
            }

            // Group collections by contract_id
            $collections_by_contract = [];
            foreach ($csv_data as $row) {
                $contract_id = (int)trim($row['계약번호'] ?? '');
                if ($contract_id > 0) {
                    if (!isset($collections_by_contract[$contract_id])) {
                        $collections_by_contract[$contract_id] = [];
                    }
                    $collections_by_contract[$contract_id][] = $row;
                }
            }

            $total_success_count = 0;
            $affected_contracts = [];

            // Prepare statement for inserting collections to be reused
            $sql_insert = "INSERT INTO collections (transaction_id, contract_id, collection_date, collection_type, amount, memo, generated_interest) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($link, $sql_insert);
            if (!$stmt_insert) {
                throw new Exception("Insert statement preparation failed: " . mysqli_error($link));
            }

            // Prepare statement for fetching contract data
            $stmt_contract_fetch = mysqli_prepare($link, "SELECT * FROM contracts WHERE id = ?");
            if (!$stmt_contract_fetch) {
                throw new Exception("Contract fetch statement preparation failed: " . mysqli_error($link));
            }

            foreach ($collections_by_contract as $contract_id => $collections) {
                // Sort collections for the current contract by date
                usort($collections, function($a, $b) {
                    return strtotime($a['입금일자'] ?? '0') - strtotime($b['입금일자'] ?? '0');
                });

                unset($contract); // Unset contract to force re-fetch for a new contract_id group
                foreach ($collections as $data) {
                    // Fetch contract data only once per contract group if not already fetched
                    if (!isset($contract)) {
                        mysqli_stmt_bind_param($stmt_contract_fetch, "i", $contract_id);
                        mysqli_stmt_execute($stmt_contract_fetch);
                        $contract_result = mysqli_stmt_get_result($stmt_contract_fetch);
                        $contract = mysqli_fetch_assoc($contract_result);
                        if(!$contract) throw new Exception("계약번호 {$contract_id}: 계약 정보를 찾을 수 없습니다.");

                        // Get the initial state for this contract from stored DB values (Incremental Update)
                        $running_balance = (float)$contract['current_outstanding_principal'];
                        $cumulative_shortfall = (float)$contract['shortfall_amount'];
                        $last_calc_date_for_loop = $contract['last_interest_calc_date'] ?? $contract['loan_date'];
                        $next_due_date_for_loop = $contract['next_due_date'];
                    }

                    $collection_date_raw = trim($data['입금일자'] ?? '');
                    $total_amount_raw = trim($data['총입금금액'] ?? '');

                    $collection_date = $collection_date_raw;
                    $total_amount = (float)str_replace(',', '', $total_amount_raw);
                    $transaction_id = uniqid('txn_', true);

                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $collection_date)) {
                        throw new Exception("계약번호 {$contract_id}: 유효하지 않은 입금일자 형식입니다 (YYYY-MM-DD). ('{$collection_date_raw}')");
                    }
                    if (!is_numeric($total_amount) || $total_amount <= 0) {
                        throw new Exception("계약번호 {$contract_id}: 유효하지 않은 총 입금금액입니다. ('{$total_amount_raw}')");
                    }

                    // Calculate interest for the period since the last transaction for this contract
                    $interest_data = calculateAccruedInterestForPeriod($link, $contract, $running_balance, $last_calc_date_for_loop, $collection_date, $next_due_date_for_loop);
                    $accrued_interest = $interest_data['total'];
                    $cumulative_shortfall += $accrued_interest;

                    // The `generated_interest` for this transaction should be the total amount of interest that was due at this point.
                    $total_interest_due_at_this_point = $cumulative_shortfall;

                    // Distribute payment
                    $interest_payment = min($total_amount, max(0, $total_interest_due_at_this_point));
                    $principal_payment = min(max(0, $total_amount - $interest_payment), $running_balance);

                    $memo_bind = "[일괄자동분개]";

                    if ($interest_payment > 0) {
                        $type = '이자';
                        mysqli_stmt_bind_param($stmt_insert, "sissdsd", $transaction_id, $contract_id, $collection_date, $type, $interest_payment, $memo_bind, $total_interest_due_at_this_point);
                        mysqli_stmt_execute($stmt_insert);
                    }
                    if ($principal_payment > 0) {
                        $type = '원금';
                        $gen_interest = 0; // No generated interest for principal
                        mysqli_stmt_bind_param($stmt_insert, "sissdsd", $transaction_id, $contract_id, $collection_date, $type, $principal_payment, $memo_bind, $gen_interest);
                        mysqli_stmt_execute($stmt_insert);
                    }

                    // Update running state for the next iteration within the same contract
                    $cumulative_shortfall -= $interest_payment;
                    $running_balance -= $principal_payment;
                    $last_calc_date_for_loop = $collection_date; // Update for next interest calculation in this loop
                    
                    // Recalculate next_due_date for the next iteration within this loop
                    $exceptions = getHolidayExceptions();
                    $next_due_date_for_loop = get_next_due_date(new DateTime($last_calc_date_for_loop), (int)$contract['agreement_date'], $exceptions)->format('Y-m-d');

                    $total_success_count++;
                }
                $affected_contracts[$contract_id] = true;
            }

            // Recalculate the final state for all affected contracts
            foreach (array_keys($affected_contracts) as $contract_id) {
                if (!recalculate_and_update_contract_state($link, $contract_id)) {
                    throw new Exception("계약 {$contract_id}의 최종 상태 업데이트에 실패했습니다.");
                }
            }

            mysqli_stmt_close($stmt_insert);
            mysqli_stmt_close($stmt_contract_fetch);

            mysqli_commit($link);
            $_SESSION['message'] = "총 {$total_success_count}건의 입금 내역이 성공적으로 업로드되었으며, " . count($affected_contracts) . "개 계약의 상태가 업데이트되었습니다.";

        } catch (Exception $e) {
            mysqli_rollback($link);
            $_SESSION['error_message'] = "일괄 입금 처리 중 오류 발생: " . $e->getMessage();
        }

    } else {
        $_SESSION['error_message'] = "파일 업로드 중 오류가 발생했습니다: " . ($_FILES['bulk_upload_file']['error'] ?? '알 수 없는 오류');
    }
    header("location: ../pages/collection_manage.php");
    exit();
}

header("location: ../pages/collection_manage.php");
exit();
?>