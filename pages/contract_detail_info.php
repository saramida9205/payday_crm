<?php
// This file is included in customer_detail.php to display contract-specific details.
// It assumes $contract, $link, and other necessary variables are available in the parent scope.

$contract_id = $contract['id'];
$today = new DateTime();
// $holidays = getHolidays(); // Deprecated

// Calculate outstanding principal using prepared statements
$stmt_principal = mysqli_prepare($link, "SELECT SUM(amount) as total FROM collections WHERE contract_id = ? AND collection_type = '원금'");
mysqli_stmt_bind_param($stmt_principal, "i", $contract_id);
mysqli_stmt_execute($stmt_principal);
$result_principal = mysqli_stmt_get_result($stmt_principal);
$principal_paid_row = mysqli_fetch_assoc($result_principal);
$principal_paid = (float)($principal_paid_row['total'] ?? 0);
mysqli_stmt_close($stmt_principal);
$outstanding_principal = (float)$contract['loan_amount'] - $principal_paid;

// Calculate interest accrued today
$interest_data_today = calculateAccruedInterest($link, $contract, $today->format('Y-m-d'));
$interest_accrued_today = $interest_data_today['total'];

// Payoff amount
$payoff_amount = $outstanding_principal + $interest_accrued_today + (float)$contract['shortfall_amount'];

// Last transaction date using prepared statements
$stmt_last_trans = mysqli_prepare($link, "SELECT MAX(collection_date) as last_date FROM collections WHERE contract_id = ?");
mysqli_stmt_bind_param($stmt_last_trans, "i", $contract_id);
mysqli_stmt_execute($stmt_last_trans);
$result_last_trans = mysqli_stmt_get_result($stmt_last_trans);
$last_trans_row = mysqli_fetch_assoc($result_last_trans);
mysqli_stmt_close($stmt_last_trans);
$last_trans_date_str = $last_trans_row['last_date'] ?? $contract['loan_date'];

// Overdue days
$overdue_days = 0;
if ($contract['status'] == 'overdue' && !empty($contract['next_due_date'])) {
    $next_due_date_obj = new DateTime($contract['next_due_date']);
    if ($today > $next_due_date_obj) {
        $overdue_days = $today->diff($next_due_date_obj)->days;
    }
}
?>

<div class="contract-details-col">
    <h4>기본 정보</h4>
    <div class="info-grid-condensed">
        <div class="info-item"><strong>대출상품명:</strong><span><?php echo htmlspecialchars($contract['product_name']); ?></span></div>
        <div class="info-item"><strong>최초대출금액:</strong><span><?php echo number_format($contract['loan_amount']); ?> 원</span></div>
        <div class="info-item"><strong>계약일:</strong><span><?php echo $contract['loan_date']; ?></span></div>
        <div class="info-item"><strong>만기일:</strong><span><?php echo $contract['maturity_date']; ?></span></div>
        <div class="info-item"><strong>약정일:</strong><span>매월 <?php echo htmlspecialchars($contract['agreement_date']); ?>일</span></div>
        <div class="info-item"><strong>대출금리:</strong><span>연 <?php echo htmlspecialchars($contract['interest_rate']); ?> %</span></div>
        <div class="info-item"><strong>연체금리:</strong><span>연 <?php echo htmlspecialchars($contract['overdue_interest_rate']); ?> %</span></div>
        <?php if (!empty($contract['rate_change_date']) && $contract['rate_change_date'] != '0000-00-00'): ?>
            <div class="info-item rate-change full-width">
                <strong>이율변경:</strong>
                <span>[<?php echo htmlspecialchars($contract['rate_change_date']); ?>] 부터</span>
                <span>(정상: <?php echo htmlspecialchars($contract['new_interest_rate']); ?>% / 연체: <?php echo htmlspecialchars($contract['new_overdue_rate']); ?>%)</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="contract-details-col">
    <h4>실시간 채권 상태 (<?php echo $today->format('Y-m-d'); ?> 기준)</h4>
    <div class="info-grid-condensed">
        <div class="info-item"><strong>대출잔액:</strong><span class="highlight-blue"><?php echo number_format($outstanding_principal); ?> 원</span></div>
        <div class="info-item"><strong>오늘까지 발생이자:</strong><span><?php echo number_format($interest_accrued_today); ?> 원</span></div>
        <div class="info-item"><strong>미수/부족금:</strong><span><?php echo number_format($contract['shortfall_amount']); ?> 원</span></div>
        <div class="info-item"><strong>오늘 완납시 금액:</strong><span class="highlight-green"><?php echo number_format($payoff_amount); ?> 원</span></div>
        <div class="info-item"><strong>마지막 거래일:</strong><span><?php echo $last_trans_date_str; ?></span></div>
        <div class="info-item"><strong>다음 약정일:</strong><span><?php echo htmlspecialchars($contract['next_due_date']); ?></span></div>
        <div class="info-item"><strong>연체일수:</strong><span class="highlight-red"><?php echo $overdue_days; ?> 일</span></div>
    </div>
</div>