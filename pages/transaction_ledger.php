<?php
include_once('header.php');
include_once('../common.php');
include_once('../process/contract_process.php');
include_once('../process/customer_process.php');

// 1. Initial Data Fetching
if (!isset($_GET['contract_id'])) {
    die("계약 ID가 지정되지 않았습니다.");
}
$contract_id = (int)$_GET['contract_id'];

$contract = getContractById($link, $contract_id);
if (!$contract) {
    die("계약 정보를 찾을 수 없습니다.");
}

$customer = getCustomerById($link, $contract['customer_id']);
if (!$customer) {
    die("고객 정보를 찾을 수 없습니다.");
}

$collections = getCollections($link, '', '', '', $contract_id);
// $holidays = getHolidays(); // Deprecated


$ledger_entries = [];
$ledger_entries[] = [
    'date' => $contract['loan_date'],
    'type' => 'loan',
    'amount' => (float)$contract['loan_amount'],
    'memo' => '대출 실행',
];

$grouped_collections = [];
foreach ($collections as $collection) {
    $key = $collection['transaction_id'];
    if (empty($key)) {
        $key = 'manual_' . $collection['collection_date'] . '_' . $collection['id'];
    }

    if (!isset($grouped_collections[$key])) {
        $grouped_collections[$key] = [
            'id' => $collection['id'],
            'contract_id' => $collection['contract_id'],
            'date' => $collection['collection_date'],
            'type' => 'payment',
            'total_paid' => 0,
            'customer_id' => $collection['customer_id'],
            'customer_name' => $collection['customer_name'],
            'memo' => $collection['memo'],
            'interest' => 0,
            'principal' => 0,
            'expense' => 0,
            'generated_interest' => 0,
            'is_grouped' => (strpos($collection['memo'], '[자동분개]') !== false || strpos($collection['memo'], '[수기일괄입금]') !== false),
            'transaction_id' => $collection['transaction_id']
        ];
    }

    $amount = (float)$collection['amount'];
    $grouped_collections[$key]['total_paid'] += $amount;

    if ($collection['collection_type'] == '이자') {
        $grouped_collections[$key]['interest'] += $amount;
    } elseif ($collection['collection_type'] == '원금') {
        $grouped_collections[$key]['principal'] += $amount;
    } elseif ($collection['collection_type'] == '경비') {
        $grouped_collections[$key]['expense'] += $amount;
    }
    $grouped_collections[$key]['generated_interest'] = max($grouped_collections[$key]['generated_interest'], (float)$collection['generated_interest']);
}

foreach ($grouped_collections as $data) {
    $ledger_entries[] = $data;
}

if (!empty($contract['rate_change_date']) && $contract['rate_change_date'] != '0000-00-00') {
    $ledger_entries[] = [
        'date' => $contract['rate_change_date'],
        'type' => 'rate_change',
        'memo' => '이율 변경 적용: ' . htmlspecialchars($contract['interest_rate']) . '%/' . htmlspecialchars($contract['overdue_interest_rate']) . '% → ' . htmlspecialchars($contract['new_interest_rate']) . '%/' . htmlspecialchars($contract['new_overdue_rate']) . '%',
    ];
}

usort($ledger_entries, function ($a, $b) {
    $date_cmp = strcmp($a['date'], $b['date']);
    if ($date_cmp != 0) return $date_cmp;
    $type_order = ['loan' => 0, 'rate_change' => 1, 'payment' => 2];
    return ($type_order[$a['type']] ?? 99) <=> ($type_order[$b['type']] ?? 99);
});

// 3. Process Ledger to generate display data
$agreement_day = (int)$contract['agreement_date'];
$running_balance = 0.0;
$cumulative_shortfall = (float)($contract['initial_shortfall'] ?? 0.0);
$total_credit = 0;
$total_interest_paid = 0;
$total_principal_paid = 0;
$total_expense_paid = 0;

$processed_ledger = [];
$last_date_str = $contract['loan_date'];

foreach ($ledger_entries as $entry) {
    if ($entry['type'] == 'loan') {
        $running_balance += $entry['amount'];
        $entry['debit'] = $entry['amount'];
        $entry['credit_interest'] = 0;
        $entry['credit_principal'] = 0;
        $entry['normal_interest_accrued'] = 0;
        $entry['overdue_interest_accrued'] = 0;
        $entry['shortfall'] = $cumulative_shortfall;
    } elseif ($entry['type'] == 'payment') {
        $entry['debit'] = $entry['total_paid'];
        $entry['credit_interest'] = $entry['interest'];
        $entry['credit_principal'] = $entry['principal'];

        $is_manual_bulk = (strpos($entry['memo'], '[수기일괄입금]') !== false);

        if ($is_manual_bulk) {
            // 수기일괄입금의 경우, generated_interest 컬럼에 '최종 부족금'이 저장되어 있습니다.
            $cumulative_shortfall = $entry['generated_interest'];
        } else {
            // 일반적인 경우 (자동분개 포함): generated_interest는 (이번달 발생이자 + 전월 미수금) 즉 '납부해야 할 총 이자/부족금' 입니다.
            // 따라서 이를 기준으로 납부액을 차감하여 잔여 부족금을 계산합니다.
            // collection_manage.php와 동일한 로직입니다.
            $cumulative_shortfall = $entry['generated_interest'] - $entry['interest'];
        }

        $running_balance -= $entry['principal'];

        $entry['shortfall'] = $cumulative_shortfall > 0 ? floor($cumulative_shortfall) : 0;

        $total_credit += $entry['total_paid'];
        $total_interest_paid += $entry['interest'];
        $total_principal_paid += $entry['principal'];
        $total_expense_paid += $entry['expense'];
    } elseif ($entry['type'] == 'rate_change') {
        // 이율 변경은 표시만 하고 계산에 직접적인 영향을 주지 않습니다.
        // 실제 이율 적용은 calculateAccruedInterestForPeriod 함수 내부에서 처리됩니다.
    }

    $entry['balance'] = $running_balance;
    $processed_ledger[] = $entry;
    $last_date_str = $entry['date'];
}

// 4. Final calculated values for display
$outstanding_principal = $running_balance;
$final_shortfall = end($processed_ledger)['shortfall'];
// Use the next_due_date directly from the contract table, which is the single source of truth.
$final_next_due_date_str = $contract['next_due_date'];

?>

<h2>입출금 원장</h2>


<div class="ledger-actions" style="margin-bottom: 15px;">
    <button type="button" class="btn btn-success" style="margin-right: 5px;" data-toggle="modal" data-target="#interestModal">예상이자</button>
    <a href="collection_manage.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-primary" style="margin-right: 5px;">입금</a>
    <button type="button" class="btn btn-warning" onclick="alert('수정 기능은 준비중입니다.');">수정</button>
</div>

<table class="info-table">
    <tr>
        <td>고객명</td>
        <td><?php echo htmlspecialchars($customer['name']); ?> (<?php echo htmlspecialchars($customer['id']); ?>)</td>
        <td>계약번호</td>
        <td><?php echo htmlspecialchars($contract['id']); ?></td>
        <td>현재상태</td>
        <td><?php echo get_status_display($contract['status']); ?></td>
    </tr>
    <tr>
        <td>약정이율</td>
        <td><?php echo htmlspecialchars($contract['interest_rate']); ?>% / <?php echo htmlspecialchars($contract['overdue_interest_rate']); ?>%</td>
        <td>최초대출</td>
        <td><?php echo number_format($contract['loan_amount']); ?> 원</td>
        <td>현재잔액</td>
        <td style="font-weight: bold;"><?php echo number_format($contract['current_outstanding_principal']); ?> 원</td>
    </tr>
    <tr>
        <td>유예약정금</td>
        <td colspan="1"><?php echo number_format($contract['deferred_agreement_amount'] ?? 0); ?> 원</td>
        <?php
        // [NEW] Calculate Today's Interest and Payoff Amount
        $today_date = date('Y-m-d');
        $interest_data = calculateAccruedInterest($link, $contract, $today_date);
        $today_accrued_interest = $interest_data['total'];

        // Calculate unpaid expenses
        $stmt_expenses = mysqli_prepare($link, "SELECT SUM(amount) as total FROM contract_expenses WHERE contract_id = ? AND is_processed = 0");
        mysqli_stmt_bind_param($stmt_expenses, "i", $contract_id);
        mysqli_stmt_execute($stmt_expenses);
        $unpaid_expenses = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_expenses))['total'];
        mysqli_stmt_close($stmt_expenses);

        $total_interest_due = $today_accrued_interest + ($contract['shortfall_amount'] ?? 0);
        $payoff_amount = ($contract['current_outstanding_principal'] ?? 0) + $total_interest_due + $unpaid_expenses;
        ?>
        <td style="background-color: #fff3cd;">오늘총이자</td>
        <td style="background-color: #fff3cd; font-weight: bold; color: red;"><?php echo number_format($total_interest_due); ?> 원</td>
        <td style="background-color: #d4edda;">오늘완납금액</td>
        <td style="background-color: #d4edda; font-weight: bold; color: blue;"><?php echo number_format($payoff_amount); ?> 원 &#40; 비용 <?php echo number_format($unpaid_expenses); ?> 원포함 &#41;</td>
    </tr>
    <?php if (!empty($contract['rate_change_date']) && $contract['rate_change_date'] != '0000-00-00'): ?>
        <tr>
            <td>이율변경</td>
            <td colspan="5" style="color: blue; font-weight: bold;">
                [<?php echo htmlspecialchars($contract['rate_change_date']); ?>] 부터 정상 <?php echo htmlspecialchars($contract['new_interest_rate']); ?>% / 연체 <?php echo htmlspecialchars($contract['new_overdue_rate']); ?>% 적용
            </td>
        </tr>
    <?php endif; ?>
    <tr>
        <td>계약일</td>
        <td><?php echo htmlspecialchars($contract['loan_date']); ?></td>
        <td>최종거래일</td>
        <td><?php echo htmlspecialchars($contract['last_interest_calc_date'] ?? '-'); ?></td>
        <td style="font-size: 0.9em;">다음입금예정일</td>
        <td><?php echo htmlspecialchars($final_next_due_date_str ?? '-'); ?></td>
    </tr>
</table>

<h3>상세 거래 내역</h3>
<table class="results-table">
    <thead>
        <tr style="background-color: #e9ecef;">
            <th style="text-align: center;">거래일자</th>
            <th style="text-align: center;">입출금 구분</th>
            <th style="text-align: center;">입.출금 금액</th>
            <th style="text-align: center;">경비</th>
            <th style="text-align: center;">이자상환금액</th>
            <th style="text-align: center;">원금상환금액</th>
            <th style="text-align: center;">적용이율</th>
            <th style="text-align: center;">현재대출잔액</th>
            <th style="text-align: center;">미수부족금</th>
            <th style="text-align: center;">메모</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($processed_ledger as $item): ?>
            <?php if ($item['type'] === 'rate_change'): ?>
                <tr class="rate-change-row">
                    <td><?php echo $item['date']; ?></td>
                    <td colspan="9"><?php echo htmlspecialchars($item['memo'] ?? ''); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td style="text-align: center;"><?php echo $item['date']; ?></td>
                    <td style="text-align: center;"><?php echo $item['type'] === 'loan' ? '대출(출금)' : '입금'; ?></td>
                    <td style="text-align: right;"><?php echo number_format($item['debit']); ?> 원</td>
                    <td style="text-align: right;"><?php echo number_format($item['expense'] ?? 0); ?> 원</td>
                    <td style="text-align: right;"><?php echo number_format($item['credit_interest']); ?> 원</td>
                    <td style="text-align: right;"><?php echo number_format($item['credit_principal']); ?> 원</td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($item['applied_rates_display'] ?? ''); ?></td>
                    <td style="text-align: right; font-weight: bold;"><?php echo number_format($item['balance']); ?> 원</td>
                    <td style="text-align: right; color: red;"><?php echo number_format($item['shortfall']); ?> 원</td>
                    <td style="text-align: left;"><?php echo htmlspecialchars($item['memo'] ?? ''); ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2" style="text-align: center; font-weight: bold;">합계</td>
            <td style="text-align: right;"><?php echo number_format($total_credit); ?> 원</td>
            <td style="text-align: right;"><?php echo number_format($total_expense_paid); ?> 원</td>
            <td style="text-align: right;"><?php echo number_format($total_interest_paid); ?> 원</td>
            <td style="text-align: right;"><?php echo number_format($total_principal_paid); ?> 원</td>
            <td colspan="4"></td>
        </tr>
    </tfoot>
</table>

<?php include_once('footer.php'); ?>