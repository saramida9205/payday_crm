<?php 
    include('../process/condition_change_process.php');
    include('header.php'); 

    // Fetch data
    $condition_changes = getConditionChanges($link);
    $contracts = getActiveContracts($link);
?>

<h2>조건변경관리</h2>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>고객명</th>
                <th>대출금액</th>
                <th>변경일</th>
                <th>변경 전 이율</th>
                <th>변경 후 이율</th>
                <th>변경 전/후 약정일</th>
                <th>변경 전 만기일</th>
                <th>변경 후 만기일</th>
                <th>변경 후 다음입금예정일</th>
                <th>사유</th>
            </tr>
        </thead>
        
        <tbody>
        <?php foreach ($condition_changes as $row) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                <td style="text-align: right;"><?php echo number_format($row['loan_amount']); ?> 원</td>
                <td><?php echo htmlspecialchars($row['change_date']); ?></td>
                <td><?php echo htmlspecialchars($row['old_interest_rate']); ?>% / <?php echo htmlspecialchars($row['old_overdue_rate'] ?? $row['old_interest_rate']); ?>%</td>
                <td><?php echo htmlspecialchars($row['new_interest_rate']); ?>% / <?php echo htmlspecialchars($row['new_overdue_rate'] ?? $row['new_interest_rate']); ?>%</td>
                <td><?php echo htmlspecialchars($row['old_agreement_date']); ?>일 → <?php echo htmlspecialchars($row['new_agreement_date']); ?>일</td>
                <td><?php echo htmlspecialchars($row['old_maturity_date']); ?></td>
                <td><?php echo htmlspecialchars($row['new_maturity_date']); ?></td>
                <td><?php echo htmlspecialchars($row['new_next_due_date']); ?></td>
                <td><?php echo htmlspecialchars($row['reason']); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<hr style="margin: 30px 0;">

<div id="condition_change_form" class="form-container">
    <h3>신규 조건 변경 추가</h3>
    <form method="post" action="../process/condition_change_process.php">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <div class="form-grid">
            <div class="form-col grid-full-width">
                <label>계약 선택</label>
                <select name="contract_id" id="contract_select" required>
                    <option value="">-- 계약을 선택하세요 --</option>
                    <?php foreach ($contracts as $contract): ?>
                        <option value="<?php echo $contract['id']; ?>" 
                                data-interest-rate="<?php echo $contract['current_interest_rate']; ?>"
                                data-overdue-rate="<?php echo $contract['current_overdue_rate']; ?>"
                                data-maturity-date="<?php echo $contract['maturity_date']; ?>"
                                data-agreement-date="<?php echo $contract['agreement_date']; ?>"
                                data-next-due-date="<?php echo $contract['next_due_date']; ?>">
                            <?php echo $contract['contract_info']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-col"><label>변경 기준일</label><input type="date" name="change_date" value="<?php echo date('Y-m-d'); ?>" required></div>
            <div class="form-col"><label>현재 이율 (%)</label><input type="text" id="current_interest_rate" disabled></div>
            <div class="form-col"><label>새 정상 이율 (%)</label><input type="number" step="0.01" id="new_interest_rate_input" name="new_interest_rate" placeholder="변경시에만 입력"></div>
            <div class="form-col"><label>새 연체 이율 (%)</label><input type="number" step="0.01" id="new_overdue_rate_input" name="new_overdue_rate" placeholder="변경시에만 입력"></div>
            <div class="form-col"><label>현재 만기일</label><input type="date" id="current_maturity_date" disabled></div>
            <div class="form-col"><label>새 만기일</label><input type="date" name="new_maturity_date"></div>
            <div class="form-col"><label>현재 약정일</label><input type="text" id="current_agreement_date" disabled></div>
            <div class="form-col"><label>새 약정일</label><input type="number" name="new_agreement_date" placeholder="숫자만 입력 (예: 5)"></div>
            <div class="form-col"><label>새 다음 입금 예정일</label><input type="date" name="new_next_due_date"></div>
            <div class="form-col grid-full-width"><label>변경 사유</label><textarea name="reason" rows="3"></textarea></div>
        </div>
        <div class="form-buttons" style="text-align: right; margin-top: 20px;">
            <button class="btn btn-primary" type="submit" name="save">저장</button>
            <a href="condition_change_manage.php" class="btn btn-secondary">취소</a>
        </div>
    </form>
</div>

<script>
document.getElementById('contract_select').addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    const interestRate = selectedOption.getAttribute('data-interest-rate');
    const overdueRate = selectedOption.getAttribute('data-overdue-rate');
    document.getElementById('current_interest_rate').value = `${interestRate}% / ${overdueRate}%`;
    document.getElementById('current_maturity_date').value = selectedOption.getAttribute('data-maturity-date');
    document.getElementById('current_agreement_date').value = selectedOption.getAttribute('data-agreement-date') + '일';
    document.querySelector('input[name="new_next_due_date"]').value = selectedOption.getAttribute('data-next-due-date');
});

document.getElementById('new_interest_rate_input').addEventListener('input', function() {
    // 새 정상 이율을 입력하면 새 연체 이율도 자동으로 채워줌
    const interestRate = parseFloat(this.value);
    if (!isNaN(interestRate)) {
        let overdueRate = interestRate + 3;
        // 이자제한법상 최고금리 20% 초과 시 20%로 제한
        if (overdueRate > 20) {
            overdueRate = 20;
        }
        document.getElementById('new_overdue_rate_input').value = overdueRate.toFixed(2);
    }
});
</script>

<?php include 'footer.php'; ?>