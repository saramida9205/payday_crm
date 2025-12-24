<?php
require_once __DIR__ . '/../common.php';

// --- Add SMS Template ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_template'])) {
    $title = trim($_POST['title']);
    $template_text = trim($_POST['template_text']);
    $contract_id = isset($_POST['contract_id']) && !empty($_POST['contract_id']) ? $_POST['contract_id'] : null;

    if (!empty($template_text)) { // Title is optional for now to prevent errors
        $sql = "INSERT INTO sms_templates (template_text, title) VALUES (?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ss", $template_text, $title);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "새 템플릿이 추가되었습니다.";
            } else {
                $_SESSION['error_message'] = "템플릿 추가에 실패했습니다.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    $redirect_url = !empty($contract_id) ? "../pages/sms.php?contract_id=" . urlencode($contract_id) : "../pages/sms.php";
    header("location: " . $redirect_url);
    exit();
}

// --- Update SMS Template ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_template'])) {
    $template_id = $_POST['template_id'];
    $title = trim($_POST['title']);
    $template_text = trim($_POST['template_text']);
    $contract_id = isset($_POST['contract_id']) && !empty($_POST['contract_id']) ? $_POST['contract_id'] : null;

    if (!empty($template_id) && !empty($template_text)) { // Title is optional
        $sql = "UPDATE sms_templates SET template_text = ?, title = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssi", $template_text, $title, $template_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "템플릿이 수정되었습니다.";
            } else {
                $_SESSION['error_message'] = "템플릿 수정에 실패했습니다.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    $redirect_url = !empty($contract_id) ? "../pages/sms.php?contract_id=" . urlencode($contract_id) : "../pages/sms.php";
    header("location: " . $redirect_url);
    exit();
}

// --- Delete SMS Template ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_template'])) {
    $template_id = $_POST['template_id'];
    $contract_id = isset($_POST['contract_id']) && !empty($_POST['contract_id']) ? $_POST['contract_id'] : null; // For redirect

    if (!empty($template_id)) {
        $sql = "DELETE FROM sms_templates WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $template_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "템플릿이 삭제되었습니다.";
            } else {
                $_SESSION['error_message'] = "템플릿 삭제에 실패했습니다.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    $redirect_url = !empty($contract_id) ? "../pages/sms.php?contract_id=" . urlencode($contract_id) : "../pages/sms.php";
    header("location: " . $redirect_url); // This was missing the variable
    exit();
}

function getContractsForSms($link, $due_days = [], $single_contract_id = null, $next_due_date = null) {
    $sql = "SELECT c.id as contract_id, c.*,
                   cu.name as customer_name, cu.phone as customer_phone, cu.bank_name, cu.account_number
            FROM contracts c
            JOIN customers cu ON c.customer_id = cu.id
            WHERE c.status IN ('active', 'overdue')";

    if ($single_contract_id !== null) {
        // 단일 계약 모드: 다른 필터 무시하고 해당 계약만 조회
        $sql .= " AND c.id = " . (int)$single_contract_id;
    } else {
        // 다중 계약 모드
        
        // 1. 상환일 필터 (우선순위 높음 or AND 조건)
        if (!empty($next_due_date)) {
            $sql .= " AND c.next_due_date = '" . mysqli_real_escape_string($link, $next_due_date) . "'";
        }

        // 2. 약정일 필터
        if (!empty($due_days)) {
            // Ensure all values are integers for security
            $safe_due_days = array_map('intval', $due_days);
            $in_clause = implode(',', $safe_due_days);
            if (!empty($in_clause)) {
                $sql .= " AND c.agreement_date IN ($in_clause)";
            }
        }
    }
    
    // 1. Fetch all contracts first
    $result = mysqli_query($link, $sql);
    $contracts = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    if (empty($contracts)) {
        return [];
    }

    // 2. Bulk fetch today's collections for these contracts
    $contract_ids = array_column($contracts, 'contract_id');
    $today_str = date('Y-m-d');
    $today_collections_map = [];

    if (!empty($contract_ids)) {
        $ids_str = implode(',', array_map('intval', $contract_ids));
        $sql_coll = "SELECT contract_id, collection_type, SUM(amount) as total_amount 
                     FROM collections 
                     WHERE contract_id IN ($ids_str) 
                       AND collection_date = '$today_str' 
                       AND deleted_at IS NULL 
                     GROUP BY contract_id, collection_type";
        $result_coll = mysqli_query($link, $sql_coll);
        while ($row = mysqli_fetch_assoc($result_coll)) {
            $today_collections_map[$row['contract_id']][] = $row;
        }
    }

    $processed_contracts = [];
    foreach ($contracts as $contract) {
        // The full contract data is now available, including current_outstanding_principal
        $outstanding_principal = (float)$contract['current_outstanding_principal'];
        
        $today = new DateTime();
        
        $interest_data_today = calculateAccruedInterest($link, $contract, $today->format('Y-m-d'));
        $interest_today = $interest_data_today['total'];

        // [NEW] Calculate unpaid expenses
        $stmt_expenses = mysqli_prepare($link, "SELECT SUM(amount) as total FROM contract_expenses WHERE contract_id = ? AND is_processed = 0");
        mysqli_stmt_bind_param($stmt_expenses, "i", $contract['contract_id']);
        mysqli_stmt_execute($stmt_expenses);
        $unpaid_expenses = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_expenses))['total'];
        mysqli_stmt_close($stmt_expenses);

        // [UPDATE] Total due today = Principal + Interest Today + Shortfall + Unpaid Expenses
        $total_due_today = $outstanding_principal + $interest_today + (float)$contract['shortfall_amount'] + $unpaid_expenses;

        $interest_data_next_due = calculateAccruedInterest($link, $contract, $contract['next_due_date']);
        $expected_interest = $interest_data_next_due['total'];

        $contract['outstanding_principal'] = number_format($outstanding_principal) . '원';
        $contract['interest_today'] = number_format($interest_today) . '원';
        $contract['total_interest_due_today'] = number_format($interest_today + (float)$contract['shortfall_amount']) . '원';
        $contract['total_due_today'] = number_format($total_due_today) . '원';
        $contract['unpaid_expenses'] = number_format($unpaid_expenses) . '원'; // [NEW]
        $contract['expected_interest'] = number_format($expected_interest) . '원';
        $contract['today_date'] = $today->format('Y-m-d');

        // [오늘납부내역] 치환자 데이터 매핑 (메모리 상에서 처리)
        $today_collections = $today_collections_map[$contract['contract_id']] ?? [];
        $today_payment_details = '';
        if (!empty($today_collections)) {
            $details = [];
            foreach ($today_collections as $coll) {
                $details[] = $coll['collection_type'] . ": " . number_format($coll['total_amount']) . "원";
            }
            $today_payment_details = implode(', ', $details);
        }
        $contract['today_payment_details'] = !empty($today_payment_details) ? $today_payment_details : '없음';

        $processed_contracts[] = $contract;
    }
    return $processed_contracts;
}

/**
 * 와이드샷(Wideshot) SMS/LMS 발송 API 호출 함수
 * @param string $apiKey API 인증키
 * @param string $userKey 메시지별 고유 키
 * @param string $senderPhone 발신번호 (테스트 시 '16882200')
 * @param string $recipientPhone 수신번호
 * @param string $title 문자 제목 (LMS/MMS일 경우)
 * @param string $message 문자 내용
 * @return array API 응답 결과 (decoded json)
 */
function sendWideshotSms($apiKey, $userKey, $senderPhone, $recipientPhone, $title, $message) {
    // MMS 발송 여부는 현재 로직에서 사용되지 않으므로, 텍스트 길이에 따라 SMS/LMS만 분기합니다.
    // 메시지 길이에 따라 SMS/LMS API URL 분기
    $is_lms = strlen($message) > 90;
    $api_url = $is_lms
        ? 'https://apimsg.wideshot.co.kr/api/v1/message/lms'      // 운영용 URL
        : 'https://apimsg.wideshot.co.kr/api/v1/message/sms';       // 운영용 URL

    // 받는 사람 번호에서 하이픈(-) 제거
    $recipientPhone = str_replace('-', '', $recipientPhone);

    // API v1 파라미터 구성 (Postman 예제 기반)
    $post_data = [
        'userKey'       => $userKey,
        'receiverTelNo' => $recipientPhone,
        'callback' => $senderPhone,
        'contents'      => $message,
    ];

    // LMS일 경우 제목 추가
    if ($is_lms) {
        if (!empty($title)) {
            $post_data['title'] = $title;
        }
    }

    // SMS/LMS는 urlencoded로, MMS는 multipart/form-data로 전송해야 합니다.
    // 현재 로직은 파일 첨부가 없으므로 urlencoded 방식만 사용합니다.
    $post_fields = http_build_query($post_data);
    $content_type_header = 'Content-Type: application/x-www-form-urlencoded';

    // 로깅: API 요청 데이터 기록
    error_log("--- [SMS SEND REQUEST] ---");
    error_log("Request URL: " . $api_url);
    error_log("Request API Key (first 10 chars): " . substr($apiKey, 0, 10) . "...");
    error_log("Request Fields: " . $post_fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields); // http_build_query로 생성된 문자열 사용
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'sejongApiKey: ' . $apiKey,
        $content_type_header // Content-Type을 동적으로 설정
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // 로깅: API 응답 데이터 기록
    error_log("[SMS SEND RESPONSE] HTTP Code: " . $http_code);
    error_log("[SMS SEND RESPONSE] Raw Response: " . $response);
    if ($curl_error) {
        error_log("[SMS SEND RESPONSE] cURL Error: " . $curl_error);
    }
    error_log("--- [SMS SEND END] ---");

    if ($response === false) {
        // cURL 자체 에러
        return ['success' => false, 'message' => 'cURL Error: ' . $curl_error];
    }

    return json_decode($response, true);
}

/**
 * SMS 발송 요청 결과에 대한 슬랙 알림을 보냅니다.
 * @param array $recipient 수신자 정보 배열 ['name' => ..., 'phone' => ...]
 * @param array $result sendWideshotSms 함수의 결과
 * @param int|null $contractId 계약 ID
 */
function notifySmsRequestToSlack($recipient, $result, $contractId) {
    $customer_info = ($recipient['name'] ?? 'N/A') . " (" . ($recipient['phone'] ?? 'N/A') . ")";
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $contract_detail_url = $base_url . "/payday/pages/contract_manage.php"; // 계약관리 페이지로 링크
    if ($contractId) {
        $contract_detail_url = $base_url . "/payday/pages/customer_detail.php?id=" . $recipient['customer_id'];
    }

    if (isset($result['code']) && $result['code'] == '200') {
        $slack_message = "ℹ️ *[SMS 발송 요청됨]*\n*대상:* {$customer_info}\n*계약:* <{$contract_detail_url}|계약 정보 보기>";
    } else {
        $error_msg = $result['message'] ?? '알 수 없는 API 오류';
        $slack_message = "🚨 *[SMS 발송 요청 실패]*\n*대상:* {$customer_info}\n*사유:* {$error_msg}";
    }
    sendSlackNotification($slack_message);
}

// --- SEND SMS (Placeholder for API integration) ---
if (isset($_POST['send_sms']) || isset($_POST['send_sms_bulk'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    
    $sender_phone = $_POST['sender_phone'];
    $title = $_POST['title'] ?? ''; // LMS/MMS title
    $redirect_param = !empty($_POST['contract_id']) ? '?contract_id=' . urlencode($_POST['contract_id']) : '';
    $company_info = get_all_company_info($link);
    $apiKey = $company_info['wideshot_api_key'];

    // API 연동 전이므로, 실제 발송 로직 대신 안내 메시지를 표시합니다.
    $is_api_ready = true; // API 연동 준비 완료로 변경

    if ($is_api_ready) {
        // --- 실제 API 연동 로직 ---
        $success_count = 0;
        $fail_count = 0;
        $error_messages = [];

        if (isset($_POST['send_sms'])) { // 단일 발송
            $recipients = json_decode($_POST['recipients'], true);
            $message = $_POST['message'];
            $userKey = substr(uniqid('s', true), 0, 12); // 12자 이내의 고유한 userkey 생성

            $recipient = $recipients[0];
            $result = sendWideshotSms($apiKey, $userKey, $sender_phone, $recipient['phone'], $title, $message);
            
            if (isset($result['code']) && $result['code'] == '200') {
                $success_count++;
                $log_status = 'pending';
            } else {
                $fail_count++;
                $error_messages[] = $result['message'] ?? '알 수 없는 오류';
                $log_status = 'failed';
            }

            // 발송 로그 기록
            $log_sql = "INSERT INTO sms_log (contract_id, customer_name, recipient_phone, message_content, userkey, api_request_result_code, api_request_result_msg, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                $contract_id = $_POST['contract_id'] ?? null;
                $api_req_code = $result['code'] ?? null;
                $api_req_msg = $result['message'] ?? ($result['sendCode'] ?? 'cURL Error');
                mysqli_stmt_bind_param($log_stmt, "isssssss", $contract_id, $recipient['name'], $recipient['phone'], $message, $userKey, $api_req_code, $api_req_msg, $log_status);
                mysqli_stmt_execute($log_stmt);
                notifySmsRequestToSlack($recipient, $result, $contract_id);
            }
        } else { // 다중 발송
            $bulk_data = json_decode($_POST['bulk_data'], true);
            foreach($bulk_data as $index => $item) {
                $userKey = substr(uniqid('b' . $index, true), 0, 12); // 각 메시지마다 12자 이내의 고유한 userkey 생성
                $result = sendWideshotSms($apiKey, $userKey, $sender_phone, $item['phone'], $title, $item['message']);
                if (isset($result['code']) && $result['code'] == '200') {
                    $success_count++;
                    $log_status = 'pending';
                } else {
                    $fail_count++;
                    $log_status = 'failed';
                }

                // 발송 로그 기록 (일괄 발송 시 contract_id, customer_name은 JS에서 받아와야 함)
                $log_sql = "INSERT INTO sms_log (contract_id, customer_name, recipient_phone, message_content, userkey, api_request_result_code, api_request_result_msg, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                    $contract_id = $item['contract_id'] ?? null;
                    $customer_name = $item['name'] ?? 'N/A';
                    $api_req_code = $result['code'] ?? null;
                    $api_req_msg = $result['message'] ?? ($result['sendCode'] ?? 'cURL Error');
                    mysqli_stmt_bind_param($log_stmt, "isssssss", $contract_id, $customer_name, $item['phone'], $item['message'], $userKey, $api_req_code, $api_req_msg, $log_status);
                    mysqli_stmt_execute($log_stmt);
                    notifySmsRequestToSlack($item, $result, $contract_id);
                }
            }
        }

        $_SESSION['message'] = "총 {$success_count}건의 SMS 발송 요청이 접수되었습니다. (실패: {$fail_count}건)";

    } else {
        // API 연동 전 안내 메시지
        $_SESSION['error_message'] = "SMS API가 아직 연동되지 않았습니다. 관리자에게 문의하세요.";
    }

    header('location: ../pages/sms.php' . $redirect_param);
    exit();
}
?>