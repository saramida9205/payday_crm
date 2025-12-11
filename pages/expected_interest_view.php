<?php
include_once('../process/customer_process.php'); 
include_once('../process/contract_process.php');
include_once('exheader.php');

// --- Initial Setup ---
if (!isset($_GET['contract_id'])) {
    echo "<div class='main-content'><h2>오류</h2><p>계약 ID가 지정되지 않았습니다.</p></div>";
    include_once('footer.php');
    exit;
}
$contract_id = $_GET['contract_id'];
$exceptions = getHolidayExceptions();

// --- Fetch Data ---
// Fetch the current contract data.
$contract_query = mysqli_query($link, "SELECT * FROM contracts WHERE id = $contract_id");
$contract = mysqli_fetch_assoc($contract_query);
if (!$contract) {
    echo "<div class='main-content'><h2>오류</h2><p>계약 정보를 찾을 수 없습니다.</p></div>";
    include_once('footer.php');
    exit;
}
$customer = getCustomerById($link, $contract['customer_id']);

// --- Base Calculations (Aligned with bond_ledger.php) ---
// 1. Calculate outstanding principal using the same function as the bond ledger.
$outstanding_principal = calculateOutstandingPrincipal($link, $contract_id, $contract['loan_amount']);

// 2. Use the shortfall amount directly from the (recalculated) contract data.
$current_shortfall = (float)$contract['shortfall_amount'];

// 3. Determine the last transaction date for interest calculation start.
$last_trans_query = mysqli_query($link, "SELECT MAX(collection_date) as last_date FROM collections WHERE contract_id = $contract_id AND deleted_at IS NULL");
$last_trans_date_str = mysqli_fetch_assoc($last_trans_query)['last_date'] ?? $contract['loan_date'];
$last_payment_date = new DateTime($last_trans_date_str);


$normal_daily_rate = (float)$contract['interest_rate'] / 100 / 365;
$overdue_daily_rate = (float)$contract['overdue_interest_rate'] / 100 / 365;
$overdue_penalty_rate = $overdue_daily_rate - $normal_daily_rate;

// --- Calculate Next Due Date ---
$agreement_day = $contract['agreement_date'];
$next_due_date = null;
if (!empty($contract['next_due_date'])) {
    $next_due_date = new DateTime($contract['next_due_date']);
} else {
    // Fallback if next_due_date is not set in contract (should ideally not happen after recalculation)
    $next_due_date = get_next_due_date(new DateTime($contract['loan_date']), $agreement_day, $exceptions);
}

// --- Base Date for Projection ---
$base_date_str = $_GET['base_date'] ?? (new DateTime())->format('Y-m-d');
$base_date = new DateTime($base_date_str);

?>

<style>
    .info-table { width: 98%; margin-bottom: 20px; margin-left: 12px; border-collapse: collapse; }
    .info-table td { padding: 8px; border: 1px solid #eee; }
    .info-table td:nth-child(odd) { background-color: #f9f9f9; font-weight: 600; width: 12%; }
    .info-table td:nth-child(even) { width: 21.33%; }
    .results-table { width: 98%; margin-left: 12px; border-collapse: collapse; font-size: 12px; }
    .results-table th, .results-table td { border: 1px solid #ddd; padding: 6px; text-align: center; }
    .results-table th { background-color: #f2f2f2; }
    .overdue-text { color: red; font-weight: bold; }
</style>

<h2 align=center>예상 이자 조회</h2>

<!-- Contract Info Table -->
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
<table class="info-table">
    <tr>
        <td>고객명</td><td><b><?php echo htmlspecialchars($customer['name']); ?> (<?php echo htmlspecialchars($customer['id']); ?>)</b></td>
        <td>계약번호</td><td><?php echo htmlspecialchars($contract['id']); ?></td>
        <td>현재상태</td><td><?php echo get_status_display($contract['status']); ?></td>
    </tr>
    <tr>
        <td>최초대출</td><td><?php echo number_format($contract['loan_amount']); ?> 원</td>
        <td>현재잔액</td><td style="font-weight: bold;"><?php echo number_format($outstanding_principal); ?> 원</td>
        <td>약정이율</td><td><?php echo htmlspecialchars($contract['interest_rate']); ?>% / <?php echo htmlspecialchars($contract['overdue_interest_rate']); ?>%</td>
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
        <td>계약일</td><td><?php echo htmlspecialchars($contract['loan_date']); ?></td>
        <td>최종거래일</td><td><?php echo $last_payment_date->format('Y-m-d'); ?></td>
        <td>다음입금예정일</td><td><b><?php echo $next_due_date ? $next_due_date->format('Y-m-d') : '-'; ?></b></td>
    </tr>
    <tr>
        <td>오늘총이자</td><td style="background-color: #fff3cd; font-weight: bold; color: red;"><?php echo number_format($total_interest_due); ?> 원</td>
        <td>미수취경비</td><td style="background-color: #fff3cd; font-weight: bold; color: red;"><?php echo number_format($unpaid_expenses); ?> 원</td>
        <td>오늘완납금액</td><td style="background-color: #d4edda; font-weight: bold; color: blue;"><?php echo number_format($payoff_amount); ?> 원</td>
    </tr>
</table>

<!-- Date Selection Form -->
<form method="get" action="expected_interest_view.php" class="form-container" style="margin-bottom: 20px;">
    <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
    <div class="search-form-flex">
        <div class="form-col">
            <label for="base_date">기준일 선택</label>
            <input type="date" id="base_date" name="base_date" value="<?php echo $base_date_str; ?>">
        </div>
        <div class="form-col">
            <button type="submit" class="btn btn-primary">조회</button>
        </div>
    </div>
</form>

<!-- Results Table -->
<div class="table-container">
    <table class="results-table">
        <thead>
            <tr>
                <th>기준일</th><th>상태</th><th>경과일</th><th>정상이자</th><th>연체이자</th><th>기존부족금</th><th>총이자</th><th>완납금액</th><th>입금</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < 90; $i++): 
                $current_date = clone $base_date;
                $current_date->modify("+$i days");

                // Use the already calculated outstanding_principal as the base for interest calculation.
                $interest_data = calculateAccruedInterestForPeriod($link, $contract, $outstanding_principal, $last_payment_date->format('Y-m-d'), $current_date->format('Y-m-d'), $next_due_date ? $next_due_date->format('Y-m-d') : $current_date->format('Y-m-d'), $current_shortfall);

                $normal_interest = $interest_data['normal'];
                $overdue_penalty_interest = $interest_data['overdue'];
                $existing_shortfall = $current_shortfall; // Use the recalculated shortfall
                $total_interest_due = floor($normal_interest) + floor($overdue_penalty_interest) + $existing_shortfall;
                $days_elapsed = $current_date->diff($last_payment_date)->days;
                $status = '<span style="color:green">정</span>';
                if ($next_due_date && $current_date > $next_due_date) {
                    $status = '<span class="overdue-text">연</span>';
                }

                $payoff_amount = $outstanding_principal + $total_interest_due;
            ?>
            <tr>
                <td><?php echo $current_date->format('Y-m-d'); ?></td>
                <td><?php echo $status; ?></td>
                <td><?php echo $days_elapsed; ?>일</td>
                <td style="text-align: right;"><?php echo number_format($normal_interest); ?></td>
                <td style="text-align: right;" class="overdue-text"><?php echo number_format($overdue_penalty_interest); ?></td>
                <td style="text-align: right;"><?php echo number_format($existing_shortfall); ?></td>
                <td style="text-align: right; font-weight: bold;"><?php echo number_format($total_interest_due); ?></td>
                <td style="text-align: right; font-weight: bold;"><?php echo number_format($payoff_amount); ?></td>
                <td>
                    <?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
                    <a href="collection_manage.php?contract_id=<?php echo $contract_id; ?>&collection_date=<?php echo $current_date->format('Y-m-d'); ?>&total_amount=<?php echo $total_interest_due; ?>" class="btn btn-sm btn-primary" style="padding: 2px 5px; font-size: 11px;">입금</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>
</div>

<?php include_once('footer.php'); ?>