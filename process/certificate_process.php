<?php
require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/contract_process.php';
require_once __DIR__ . '/customer_process.php';

header('Content-Type: application/json');

$contract_id = $_GET['contract_id'] ?? null;
$type = $_GET['type'] ?? '';

if (!$contract_id || !$type) {
    echo json_encode(['success' => false, 'message' => '계약 ID 또는 증명서 종류가 지정되지 않았습니다.']);
    exit;
}

$contract = getContractById($link, $contract_id);
if (!$contract) {
    echo json_encode(['success' => false, 'message' => '계약 정보를 찾을 수 없습니다.']);
    exit;
}

$customer = getCustomerById($link, $contract['customer_id']);
if (!$customer) {
    echo json_encode(['success' => false, 'message' => '고객 정보를 찾을 수 없습니다.']);
    exit;
}

$today = new DateTime(); // 현재 날짜 객체 생성 (누락된 부분)

// 현재 연체일수 계산
    $overdue_days = 0;
    if (!empty($contract['next_due_date'])) {
        $next_due_date_obj = new DateTime($contract['next_due_date']);
        $today_start_of_day = (clone $today)->setTime(0, 0, 0); // 시간 부분을 제거하여 날짜만 비교
        if ($today_start_of_day > $next_due_date_obj) {
            $overdue_days = $today_start_of_day->diff($next_due_date_obj)->days;
        }
    }

// --- 데이터 준비 ---
$today_ymd = date('Y-m-d');
$today_display = date('Y년 m월 d일');
$company_info = get_all_company_info($link);

// 최종거래일(완납일자) 조회
$stmt_last_date = mysqli_prepare($link, "SELECT MAX(collection_date) as last_date FROM collections WHERE contract_id = ? AND deleted_at IS NULL");
mysqli_stmt_bind_param($stmt_last_date, "i", $contract_id);
mysqli_stmt_execute($stmt_last_date);
$last_collection_date = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_last_date))['last_date'] ?? $contract['loan_date'];
mysqli_stmt_close($stmt_last_date);

// --- 연체금액 및 완납금액 계산 로직 (DB 저장 값 기준) ---
$cert_state = getContractStateForCertificate($link, $contract_id);
$outstanding_principal = (float)($cert_state['current_outstanding_principal'] ?? 0.0);
$existing_shortfall = (float)($cert_state['shortfall_amount'] ?? 0.0);
$last_calc_date = $cert_state['last_interest_calc_date'] ?? $contract['loan_date'];

$interest_data_until_today = calculateAccruedInterestForPeriod(
    $link,
    $contract,
    $outstanding_principal,
    $last_calc_date,
    $today_ymd,
    $contract['next_due_date'] ?? $today_ymd
);
$total_interest_due_today = $existing_shortfall + $interest_data_until_today['total'];

// --- 완납금액 계산 ---
$payoff_amount = $outstanding_principal + $total_interest_due_today;

// --- 법인 인감 이미지 처리 ---
$seal_path = '../uploads/company/seal.png';
$seal_html = '';
if (file_exists(__DIR__ . '/' . $seal_path)) {
    $seal_html = '<img src="' . $seal_path . '?t=' . time() . '" alt="직인" style="width: 70px; height: 70px; vertical-align: middle;">';
}

// --- 치환자 배열 생성 ---
$placeholders = [
    '[고객명]' => $customer['name'],
    '[계약번호]' => $contract['id'],
    '[대출원금]' => number_format($contract['loan_amount']) . '원',
    '[대출잔액]' => number_format($outstanding_principal) . '원',
    '[연체금액]' => number_format(floor($existing_shortfall)) . '원', // DB에 저장된 미납이자
    '[대출일]' => $contract['loan_date'],
    '[만기일]' => $contract['maturity_date'],
    '[약정일]' => $contract['agreement_date'] . '일',
    '[정상금리]' => $contract['interest_rate'] . '%',
    '[연체금리]' => $contract['overdue_interest_rate'] . '%',
    '[현재연체일수]' => $overdue_days . '일',
    '[고객주소]' => $customer['address_registered'],
    '[고객연락처]' => $customer['phone'],
    '[주민번호]' => $customer['resident_id_partial'],
    '[회사명]' => $company_info['company_name'],
    '[회사주소]' => $company_info['company_address'],
    '[회사연락처]' => $company_info['company_phone'],
    '[회사대표]' => $company_info['ceo_name'],
    '[오늘날짜]' => $today_display,
    '[담당자명]' => $company_info['manager_name'],
    '[담당자연락처]' => $company_info['manager_phone'],
    '[완납일자]' => $last_collection_date,
    '[최근거래일]' => $last_collection_date,
    '[오늘이자금액]' => number_format(floor($total_interest_due_today)) . '원', // 오늘까지 발생한 총 이자
    '[완납금액]' => number_format(floor($payoff_amount)) . '원',
    '[법인인감]' => $seal_html,
];

if ($type === 'repayment_schedule') {
    $schedule_table = '<table style="width:100%; border-collapse: collapse; font-size: 12px; text-align: center;">';
    $schedule_table .= '<thead><tr style="background-color: #f2f2f2;">
                            <th style="border: 1px solid #ddd; padding: 8px;">회차</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">납입예정일</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">상환원금</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">상환이자</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">월납입금</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">잔액</th>
                        </tr></thead><tbody>';

    $schedule_balance = $outstanding_principal;
    $schedule_last_date_str = $last_calc_date; // 오늘 기준이 아닌, 마지막 계산일 기준
    $exceptions = getHolidayExceptions();
    $agreement_day = (int)$contract['agreement_date'];

    for ($i = 1; $i <= 12; $i++) { // Generate for next 12 months
        $next_due_date_obj = get_next_due_date(new DateTime($schedule_last_date_str), $agreement_day, $exceptions);
        $next_due_date_str = $next_due_date_obj->format('Y-m-d');

        $interest_data = calculateAccruedInterestForPeriod(
            $link, $contract, $schedule_balance, $schedule_last_date_str, $next_due_date_str, $next_due_date_str
        );
        $interest_payment = $interest_data['total'];
        $principal_payment = 0; // Assuming interest-only for this schedule
        $total_payment = $interest_payment + $principal_payment;

        $schedule_table .= '<tr>';
        $schedule_table .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $i . '</td>';
        $schedule_table .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $next_due_date_str . '</td>';
        $schedule_table .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . number_format($principal_payment) . '</td>';
        $schedule_table .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . number_format($interest_payment) . '</td>';
        $schedule_table .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . number_format($total_payment) . '</td>';
        $schedule_table .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . number_format($schedule_balance) . '</td>';
        $schedule_table .= '</tr>';

        $schedule_last_date_str = $next_due_date_str;
    }

    $schedule_table .= '</tbody></table>';
    $placeholders['[상환스케줄테이블]'] = $schedule_table;
}

// --- 거래내역서 특별 처리 ---
if ($type === 'transaction_history') {
    $start_date = !empty($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = !empty($_GET['end_date']) ? $_GET['end_date'] : null;

    $ledger_data = get_transaction_ledger_data($link, $contract_id, $start_date, $end_date);
    $history_table_html = '<table style="width:100%; border-collapse: collapse; font-size: 12px; text-align: center;">';
    $history_table_html .= '<thead><tr style="background-color: #f2f2f2;">
                                <th style="border: 1px solid #ddd; padding: 8px;">거래일</th>
                                <th style="border: 1px solid #ddd; padding: 8px;">거래구분</th>
                                <th style="border: 1px solid #ddd; padding: 8px;">입금액</th>
                                <th style="border: 1px solid #ddd; padding: 8px;">이자상환</th>
                                <th style="border: 1px solid #ddd; padding: 8px;">원금상환</th>
                                <th style="border: 1px solid #ddd; padding: 8px;">거래후잔액</th>
                            </tr></thead>';
    $history_table_html .= '<tbody>';

    foreach ($ledger_data as $entry) {
        $history_table_html .= '<tr>';
        $history_table_html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $entry['date'] . '</td>';
        $history_table_html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $entry['description'] . '</td>';
        if ($entry['description'] === '대출실행' || $entry['description'] === '이월잔액') {
            $history_table_html .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . number_format($entry['debit']) . '</td>';
            $history_table_html .= '<td style="border: 1px solid #ddd; padding: 8px;">-</td><td style="border: 1px solid #ddd; padding: 8px;">-</td>';
        } else {
            $history_table_html .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . number_format($entry['credit']) . '</td>';
            $history_table_html .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . number_format($entry['interest_paid']) . '</td>';
            $history_table_html .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . number_format($entry['principal_paid']) . '</td>';
        }
        $history_table_html .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . number_format($entry['balance']) . '</td>';
        $history_table_html .= '</tr>';
    }

    $history_table_html .= '</tbody></table>';

    // 거래내역서용 치환자 추가
    $placeholders['[거래내역테이블]'] = $history_table_html;
    $placeholders['[조회시작일]'] = $start_date ?: $contract['loan_date'];
    $placeholders['[조회종료일]'] = $end_date ?: date('Y-m-d');
}


// --- 템플릿 가져오기 ---
$template_html = get_certificate_template($type);
if ($template_html === null) {
    // DB에 템플릿이 없는 경우, 초기화 시도
    initialize_certificate_templates();
    $template_html = get_certificate_template($type);
    if ($template_html === null) {
        $template_html = "<p>선택하신 '{$type}' 증명서의 템플릿이 아직 준비되지 않았습니다. 템플릿 관리 메뉴에서 내용을 작성해주세요.</p>";
    }
}

// --- 치환자 변환 ---
$final_html = str_replace(array_keys($placeholders), array_values($placeholders), $template_html);

echo json_encode(['success' => true, 'html' => $final_html]);
exit;

?>