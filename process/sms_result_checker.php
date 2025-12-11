<?php
require_once __DIR__ . '/../common.php';

header('Content-Type: application/json');

/**
 * 와이드샷(Wideshot) 발송 결과 조회 API 호출 함수
 * @param string $apiKey API 인증키
 * @param string $userKey 조회할 메시지의 고유 키
 * @return array API 응답 결과 (decoded json)
 */
function getWideshotSmsResult($apiKey, $userKey) {
    // API 문서에 따르면 v3가 최신 버전입니다.
    $api_url = 'https://apimsg.wideshot.co.kr/api/v3/message/result?sendCode=' . urlencode($userKey); // 운영용 URL로 설정

    // 로깅: API 요청 데이터 기록
    error_log("--- [SMS RESULT CHECK REQUEST] ---");
    error_log("Request URL: " . $api_url);
    error_log("Request API Key (first 10 chars): " . substr($apiKey, 0, 10) . "...");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'sejongApiKey: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    error_log("[SMS RESULT CHECK RESPONSE] HTTP Code: " . $http_code);
    error_log("[SMS RESULT CHECK RESPONSE] Raw Response: " . $response);
    if ($curl_error) { error_log("[SMS RESULT CHECK RESPONSE] cURL Error: " . $curl_error); }
    error_log("--- [SMS RESULT CHECK END] ---");

    if ($response === false) {
        return ['success' => false, 'message' => 'cURL Error: ' . $curl_error];
    }

    $decoded_response = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'Invalid JSON response from API.'];
    }

    return $decoded_response;
}

/**
 * API 결과 코드를 기반으로 상태와 한글 메시지를 반환합니다.
 * @param string $resultCode API로부터 받은 결과 코드
 * @return array ['status' => 'sent'|'failed'|'pending', 'message' => '한글 설명']
 */
function processResultCode($resultCode) {
    // API 문서의 결과 코드 표를 기반으로 작성합니다.
    // 주요 코드만 우선적으로 처리하고, 나머지는 '기타 실패'로 처리합니다.
    if ($resultCode == '100') {
        return ['status' => 'sent', 'message' => '성공'];
    }
    if (empty($resultCode)) {
        return ['status' => 'pending', 'message' => '결과 수신 대기중'];
    }

    $errorMessages = [
        '201' => '착신 가입자 없음(결번)',
        '208' => '사용 정지된 번호',
        '210' => '사전 미등록 발신번호 사용',
        '301' => '단말기 메시지함 FULL',
        '302' => '단말기 전원 꺼짐/음영지역',
        '503' => '스팸 처리됨',
        '1003' => '유효하지 않은 발신 프로필 키',
        '3018' => '메시지 전송 불가 (친구관계 아님 등)',
        '3019' => '카카오톡 유저가 아님',
        '3020' => '알림톡 수신 차단',
        '8836' => 'RCS 미지원 단말',
        '8837' => '단말기기로 RCS 메시지 전송 불가',
    ];

    $message = $errorMessages[$resultCode] ?? "실패 (코드: {$resultCode})";
    return ['status' => 'failed', 'message' => $message];
}

/**
 * 데이터베이스의 sms_log를 업데이트합니다.
 * @param mysqli $link DB 연결
 * @param int $logId 로그 ID
 * @param string $status 'sent' 또는 'failed'
 * @param string $finalResultCode API 결과 코드
 * @param string $finalResultMsg 처리된 메시지
 * @return bool 성공 여부
 */
function updateSmsLog($link, $logId, $status, $finalResultCode, $finalResultMsg) {
    $sql = "UPDATE sms_log SET status = ?, final_result_code = ?, final_result_msg = ?, checked_time = NOW() WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "sssi", $status, $finalResultCode, $finalResultMsg, $logId);
        return mysqli_stmt_execute($stmt);
    }
    return false;
}

/**
 * SMS 발송 결과에 대한 슬랙 알림을 보냅니다.
 * @param mysqli $link DB 연결
 * @param int $logId 로그 ID
 * @param array $processedResult processResultCode의 결과
 */
function notifySmsResultToSlack($link, $logId, $processedResult, $contractId) {
    // 'pending' 상태일 때는 알림을 보내지 않습니다.
    if ($processedResult['status'] === 'pending') {
        return;
    }

    $log_sql = "SELECT customer_name, recipient_phone, customer_id FROM sms_log LEFT JOIN contracts ON sms_log.contract_id = contracts.id WHERE sms_log.id = ?";
    $stmt = mysqli_prepare($link, $log_sql);
    mysqli_stmt_bind_param($stmt, "i", $logId);
    mysqli_stmt_execute($stmt);
    $log_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $customer_info = ($log_data['customer_name'] ?? 'N/A') . " (" . ($log_data['recipient_phone'] ?? 'N/A') . ")";

    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $contract_detail_url = $base_url . "/payday/pages/customer_detail.php?id=" . ($log_data['customer_id'] ?? '');

    $payload = [
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '' // 내용은 아래에서 채웁니다.
                ]
            ]
        ]
    ];

    if ($processedResult['status'] === 'sent') {
        $payload['blocks'][0]['text']['text'] = "✅ *[SMS 발송 성공]*\n*대상:* {$customer_info}\n*계약:* <{$contract_detail_url}|계약 상세 보기>";
    } elseif ($processedResult['status'] === 'failed') {
        $payload['blocks'][0]['text']['text'] = "❌ *[SMS 발송 실패]*\n*대상:* {$customer_info}\n*사유:* " . $processedResult['message'] . "\n*계약:* <{$contract_detail_url}|계약 상세 보기>";
    }

    sendSlackNotification($payload);
}

// --- Main Logic ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$company_info = get_all_company_info($link);
$apiKey = $company_info['wideshot_api_key'];

if ($action === 'check_single') {
    $log_id = $_POST['log_id'] ?? 0;
    $userkey = $_POST['userkey'] ?? '';

    if (empty($log_id) || empty($userkey)) {
        echo json_encode(['success' => false, 'message' => '필수 파라미터가 누락되었습니다.']);
        exit;
    }

    $api_result = getWideshotSmsResult($apiKey, $userkey); // userkey가 sendCode로 사용됨

    if (isset($api_result['code']) && $api_result['code'] == '200' && isset($api_result['data'])) {
        $resultData = $api_result['data'];
        $resultCode = $resultData['resultCode'] ?? '';

        // 대체발송이 성공한 경우
        if (empty($resultCode) && !empty($resultData['resendResultCode'])) {
            $resultCode = $resultData['resendResultCode'];
        }

        $processed = processResultCode($resultCode);
        
        updateSmsLog($link, $log_id, $processed['status'], $resultCode, $processed['message']);
        
        // Get contract_id from the log to build the URL
        $stmt_get_contract = mysqli_prepare($link, "SELECT contract_id FROM sms_log WHERE id = ?");
        mysqli_stmt_bind_param($stmt_get_contract, "i", $log_id);
        mysqli_stmt_execute($stmt_get_contract);
        $contract_id_for_slack = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get_contract))['contract_id'] ?? null;
        notifySmsResultToSlack($link, $log_id, $processed, $contract_id_for_slack);

        echo json_encode([
            'success' => true,
            'status_badge' => getStatusBadge($processed['status']),
            'final_result_msg' => $processed['message']
        ]);

    } else {
        $errorMessage = $api_result['message'] ?? 'API로부터 유효한 응답을 받지 못했습니다.';
        echo json_encode(['success' => false, 'message' => $errorMessage]);
    }
    exit;
}

if ($action === 'check_pending_bulk') {
    // 이 부분은 서버에서 직접 실행(cron job)하는 것을 가정합니다.
    // 웹 브라우저 타임아웃을 피하기 위해 실행 시간 제한을 늘립니다.
    set_time_limit(300);

    $sql = "SELECT id, userkey, contract_id FROM sms_log WHERE status = 'pending' AND request_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $result = mysqli_query($link, $sql);
    $pending_logs = mysqli_fetch_all($result, MYSQLI_ASSOC);

    $success_count = 0;
    $not_updated_count = 0;

    foreach ($pending_logs as $log) {
        // API 과호출을 방지하기 위해 약간의 딜레이를 줍니다.
        usleep(100000); // 0.1초

        $api_result = getWideshotSmsResult($apiKey, $log['userkey']);
        
        if (isset($api_result['code']) && $api_result['code'] == '200' && isset($api_result['data'])) {
            $resultData = $api_result['data'];
            $resultCode = $resultData['resultCode'] ?? '';
            if (empty($resultCode) && !empty($resultData['resendResultCode'])) {
                $resultCode = $resultData['resendResultCode'];
            }

            $processed = processResultCode($resultCode);
            if ($processed['status'] !== 'pending') {
                updateSmsLog($link, $log['id'], $processed['status'], $resultCode, $processed['message']);
                notifySmsResultToSlack($link, $log['id'], $processed, $log['contract_id']);
                $success_count++;
            } else {
                $not_updated_count++;
            }
        } else {
            $not_updated_count++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "일괄 결과 확인 완료. 상태 업데이트: {$success_count}건, 변경 없음: {$not_updated_count}건"
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => '알 수 없는 요청입니다.']);
?>