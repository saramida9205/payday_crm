<?php
include 'header.php';
// common.php에 있는 함수들을 사용하기 위해 포함합니다.
// header.php에서 이미 포함되었을 수 있으나, 명시적으로 한 번 더 확인합니다.
require_once '../common.php';

// 데이터베이스 연결은 common.php 또는 config.php에서 처리됩니다.
global $link;

// --- 날짜 파라미터 설정 ---
// 채권 통계 기준일 (기본값: 어제)
$snapshot_date_str = $_GET['snapshot_date'] ?? date('Y-m-d', strtotime('-1 day'));
// 자금 현황 기간 (기본값: 어제 하루)
$fund_start_date_str = $_GET['fund_start_date'] ?? date('Y-m-d', strtotime('-1 day'));
$fund_end_date_str = $_GET['fund_end_date'] ?? date('Y-m-d', strtotime('-1 day'));

// --- 1. 채권 통계 (Snapshot) ---
// 조회 기준일(`snapshot_date`) 시점의 정확한 데이터를 계산하는 통합 쿼리

// SQL 변수를 사용하여 반복되는 파라미터를 줄이고 오류 가능성을 낮춥니다.
// SQL 변수를 사용하지 않고 직접 파라미터 바인딩을 사용하여 SQL Injection 방지
$bond_stats_sql = "
    SELECT
        -- 전체 채권
        COUNT(c.id) AS total_count,
        SUM(c.loan_amount - IFNULL(p.principal_paid, 0)) AS total_amount,
        -- 정상 채권
        COUNT(CASE WHEN c.next_due_date >= ? THEN c.id END) AS normal_count,
        SUM(CASE WHEN c.next_due_date >= ? THEN c.loan_amount - IFNULL(p.principal_paid, 0) ELSE 0 END) AS normal_amount,
        -- 전체 연체 채권
        COUNT(CASE WHEN c.next_due_date < ? THEN c.id END) AS overdue_total_count,
        SUM(CASE WHEN c.next_due_date < ? THEN c.loan_amount - IFNULL(p.principal_paid, 0) ELSE 0 END) AS overdue_total_amount,
        -- 30일 이하 연체
        COUNT(CASE WHEN c.next_due_date < ? AND DATEDIFF(?, c.next_due_date) <= 30 THEN c.id END) AS overdue_30_count,
        SUM(CASE WHEN c.next_due_date < ? AND DATEDIFF(?, c.next_due_date) <= 30 THEN c.loan_amount - IFNULL(p.principal_paid, 0) ELSE 0 END) AS overdue_30_amount,
        -- 31-60일 연체
        COUNT(CASE WHEN c.next_due_date < ? AND DATEDIFF(?, c.next_due_date) BETWEEN 31 AND 60 THEN c.id END) AS overdue_60_count,
        SUM(CASE WHEN c.next_due_date < ? AND DATEDIFF(?, c.next_due_date) BETWEEN 31 AND 60 THEN c.loan_amount - IFNULL(p.principal_paid, 0) ELSE 0 END) AS overdue_60_amount,
        -- 61-90일 연체
        COUNT(CASE WHEN c.next_due_date < ? AND DATEDIFF(?, c.next_due_date) BETWEEN 61 AND 90 THEN c.id END) AS overdue_90_count,
        SUM(CASE WHEN c.next_due_date < ? AND DATEDIFF(?, c.next_due_date) BETWEEN 61 AND 90 THEN c.loan_amount - IFNULL(p.principal_paid, 0) ELSE 0 END) AS overdue_90_amount,
        -- 91-180일 연체
        COUNT(CASE WHEN c.next_due_date < ? AND DATEDIFF(?, c.next_due_date) BETWEEN 91 AND 180 THEN c.id END) AS overdue_180_count,
        SUM(CASE WHEN c.next_due_date < ? AND DATEDIFF(?, c.next_due_date) BETWEEN 91 AND 180 THEN c.loan_amount - IFNULL(p.principal_paid, 0) ELSE 0 END) AS overdue_180_amount,
        -- 181일 이상 연체
        COUNT(CASE WHEN c.next_due_date < ? AND DATEDIFF(?, c.next_due_date) >= 181 THEN c.id END) AS overdue_181_count,
        SUM(CASE WHEN c.next_due_date < ? AND DATEDIFF(?, c.next_due_date) >= 181 THEN c.loan_amount - IFNULL(p.principal_paid, 0) ELSE 0 END) AS overdue_181_amount
    FROM contracts c
    LEFT JOIN (
        SELECT contract_id, SUM(amount) as principal_paid
        FROM collections
        WHERE collection_type = '원금' AND deleted_at IS NULL AND collection_date <= ?
        GROUP BY contract_id
    ) p ON c.id = p.contract_id
    WHERE c.loan_date <= ?
    AND (c.loan_amount - IFNULL(p.principal_paid, 0)) > 1 -- 잔액이 1원 초과인 계약만 (완납 제외)
";

$stmt_bond = mysqli_prepare($link, $bond_stats_sql);
if (!$stmt_bond) {
    die("SQL prepare failed: " . mysqli_error($link));
}

// Bind parameters: There are 32 placeholders (checked manually)
// 1,2: normal (>= snapshot)
// 3,4: overdue total (< snapshot)
// 5,6,7,8: overdue 30 (< snapshot, datediff(snapshot, next_due))
// 9,10,11,12: overdue 60
// 13,14,15,16: overdue 90
// 17,18,19,20: overdue 180
// 21,22,23,24: overdue 181
// 25: subquery collection_date
// 26: where loan_date
// Total 26 placeholders? Let's recount carefully.
// normal: 2
// overdue total: 2
// overdue 30: 4 (next_due < ?, DATEDIFF(?, ...), next_due < ?, DATEDIFF(?, ...))
// overdue 60: 4
// overdue 90: 4
// overdue 180: 4
// overdue 181: 4
// subquery: 1
// where: 1
// Total: 2 + 2 + 4 + 4 + 4 + 4 + 4 + 1 + 1 = 26

$s = $snapshot_date_str;
mysqli_stmt_bind_param($stmt_bond, "ssssssssssssssssssssssssss", 
    $s, $s, 
    $s, $s, 
    $s, $s, $s, $s, 
    $s, $s, $s, $s, 
    $s, $s, $s, $s, 
    $s, $s, $s, $s, 
    $s, $s, $s, $s, 
    $s, $s
);

mysqli_stmt_execute($stmt_bond);
$bond_stats_result = mysqli_stmt_get_result($stmt_bond);
$bond_stats = mysqli_fetch_assoc($bond_stats_result);
mysqli_stmt_close($stmt_bond);

// --- 2. 자금 현황 (Period) ---
$fund_stats = [
    'total_deposits' => 0, 'expense_deposits' => 0, 'interest_deposits' => 0, 'principal_deposits' => 0,
    'total_withdrawals' => 0, 'new_contracts_count' => 0
];

// 기간 내 입금 통계
$sql_deposits = "SELECT SUM(amount) as total, SUM(CASE WHEN collection_type = '경비' THEN amount ELSE 0 END) as expense, SUM(CASE WHEN collection_type = '이자' THEN amount ELSE 0 END) as interest, SUM(CASE WHEN collection_type = '원금' THEN amount ELSE 0 END) as principal FROM collections WHERE collection_date BETWEEN ? AND ? AND deleted_at IS NULL";
$stmt_deposits = mysqli_prepare($link, $sql_deposits);
mysqli_stmt_bind_param($stmt_deposits, "ss", $fund_start_date_str, $fund_end_date_str);
mysqli_stmt_execute($stmt_deposits);
$deposit_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_deposits));
mysqli_stmt_close($stmt_deposits);
$fund_stats['total_deposits'] = $deposit_data['total'] ?? 0;
$fund_stats['expense_deposits'] = $deposit_data['expense'] ?? 0;
$fund_stats['interest_deposits'] = $deposit_data['interest'] ?? 0;
$fund_stats['principal_deposits'] = $deposit_data['principal'] ?? 0;

// 기간 내 출금 통계 (신규 대출)
$sql_withdrawals = "SELECT COUNT(id) as count, SUM(loan_amount) AS total FROM contracts WHERE loan_date BETWEEN ? AND ?";
$stmt_withdrawals = mysqli_prepare($link, $sql_withdrawals);
mysqli_stmt_bind_param($stmt_withdrawals, "ss", $fund_start_date_str, $fund_end_date_str);
mysqli_stmt_execute($stmt_withdrawals);
$withdrawal_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_withdrawals));
mysqli_stmt_close($stmt_withdrawals);
$fund_stats['total_withdrawals'] = $withdrawal_data['total'] ?? 0;
$fund_stats['new_contracts_count'] = $withdrawal_data['count'] ?? 0;

// 비율 계산 함수
function calculate_ratio($numerator, $denominator) {
    return $denominator > 0 ? round(($numerator / $denominator) * 100, 2) : 0;
}
?>

<h2>업무일보</h2>

<div class="form-container">
    <form method="GET" action="" id="reportForm">
        <div class="search-form-flex" style="justify-content: center; align-items: flex-end;">
            <div class="form-col"><label>채권 기준일</label><input type="date" name="snapshot_date" value="<?php echo htmlspecialchars($snapshot_date_str); ?>"></div>
            <div class="form-col"><label>자금 시작일</label><input type="date" name="fund_start_date" value="<?php echo htmlspecialchars($fund_start_date_str); ?>"></div>
            <div class="form-col"><label>자금 종료일</label><input type="date" name="fund_end_date" id="fund_end_date" value="<?php echo htmlspecialchars($fund_end_date_str); ?>"></div>
            <div class="form-col" style="display: flex; align-items: center; margin-bottom: 5px;">
                <label style="cursor: pointer;">
                    <input type="checkbox" id="today_checkbox" style="vertical-align: middle; margin-right: 5px;"> 오늘
                </label>
            </div>
            <div class="form-col"><button type="submit" class="btn btn-primary">조회</button></div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const todayCheckbox = document.getElementById('today_checkbox');
    const snapshotDateInput = document.querySelector('input[name="snapshot_date"]');
    const fundStartDateInput = document.querySelector('input[name="fund_start_date"]');
    const fundEndDateInput = document.querySelector('input[name="fund_end_date"]');

    // 오늘 날짜 구하기 (YYYY-MM-DD)
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const todayStr = `${year}-${month}-${day}`;

    // 체크박스 변경 이벤트
    todayCheckbox.addEventListener('change', function() {
        if (this.checked) {
            snapshotDateInput.value = todayStr;
            fundStartDateInput.value = todayStr;
            fundEndDateInput.value = todayStr;
        }
    });

    // 날짜 입력 필드 변경 시 체크박스 해제 (선택 사항)
    const dateInputs = [snapshotDateInput, fundStartDateInput, fundEndDateInput];
    dateInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (input.value !== todayStr) {
                todayCheckbox.checked = false;
            }
        });
    });
});
</script>

<!-- 채권 통계 섹션 -->
<div class="form-container" style="margin-top: 30px;">
    <h4 class="section-title">채권 통계 (<?php echo htmlspecialchars($snapshot_date_str); ?> 기준)</h4>
    <div class="table-container" style="margin-top: 15px;">
        <table class="report-table">
            <thead>
                <!-- 테이블 헤더 -->
                <tr>
                    <th>구분</th>
                    <th>채권총계</th>
                    <th>정상채권</th>
                    <th>전체연체총계</th>
                    <th>30일이내연체</th>
                    <th>60일이내연체</th>
                    <th>90일이내연체</th>
                    <th>180일이내연체</th>
                    <th>181일이상연체</th>
                </tr>
            </thead>
            <tbody>
                <!-- 테이블 내용 -->
                <tr>
                    <td class="category">건수 (건)</td>
                    <td><?php echo number_format($bond_stats['total_count'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['normal_count'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_total_count'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_30_count'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_60_count'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_90_count'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_180_count'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_181_count'] ?? 0); ?></td>
                </tr>
                <tr>
                    <td class="category">금액 (원)</td>
                    <td><?php echo number_format($bond_stats['total_amount'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['normal_amount'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_total_amount'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_30_amount'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_60_amount'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_90_amount'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_180_amount'] ?? 0); ?></td>
                    <td><?php echo number_format($bond_stats['overdue_181_amount'] ?? 0); ?></td>
                </tr>
                <tr class="total-row">
                    <td class="category">비율 (%)</td>
                    <td>100.00</td>
                    <td><?php echo calculate_ratio($bond_stats['normal_amount'] ?? 0, $bond_stats['total_amount'] ?? 0); ?></td>
                    <td><?php echo calculate_ratio($bond_stats['overdue_total_amount'] ?? 0, $bond_stats['total_amount'] ?? 0); ?></td>
                    <td><?php echo calculate_ratio($bond_stats['overdue_30_amount'] ?? 0, $bond_stats['total_amount'] ?? 0); ?></td>
                    <td><?php echo calculate_ratio($bond_stats['overdue_60_amount'] ?? 0, $bond_stats['total_amount'] ?? 0); ?></td>
                    <td><?php echo calculate_ratio($bond_stats['overdue_90_amount'] ?? 0, $bond_stats['total_amount'] ?? 0); ?></td>
                    <td><?php echo calculate_ratio($bond_stats['overdue_180_amount'] ?? 0, $bond_stats['total_amount'] ?? 0); ?></td>
                    <td><?php echo calculate_ratio($bond_stats['overdue_181_amount'] ?? 0, $bond_stats['total_amount'] ?? 0); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 자금 현황 섹션 -->
<div class="form-container" style="margin-top: 30px;">
    <h4 class="section-title">자금 현황 (<?php echo htmlspecialchars($fund_start_date_str); ?> ~ <?php echo htmlspecialchars($fund_end_date_str); ?>)</h4>
    <div class="table-container" style="max-width: 600px; margin-top: 15px;">
            <table class="report-table">
                <tbody>
                    <tr>
                        <td class="category" style="width: 20%;">총 입금액</td>
                        <td style="text-align: right; font-weight: bold; color: blue;"><?php echo number_format($fund_stats['total_deposits']); ?> 원</td>
                    </tr>
                    <tr>
                        <td class="category" style="padding-left: 40px;">- 비용 입금</td>
                        <td style="text-align: right;"><?php echo number_format($fund_stats['expense_deposits']); ?> 원</td>
                    </tr>
                    <tr>
                        <td class="category" style="padding-left: 40px;">- 이자 입금</td>
                        <td style="text-align: right;"><?php echo number_format($fund_stats['interest_deposits']); ?> 원</td>
                    </tr>
                    <tr>
                        <td class="category" style="padding-left: 40px;">- 원금 입금</td>
                        <td style="text-align: right;"><?php echo number_format($fund_stats['principal_deposits']); ?> 원</td>
                    </tr>
                    <tr>
                        <td class="category" style="width: 20%;">총 출금액</td>
                        <td style="text-align: right; font-weight: bold; color: red;"><?php echo number_format($fund_stats['total_withdrawals']); ?> 원</td>
                    </tr>
                    <tr>
                        <td class="category" style="padding-left: 40px;">- 신규 대출</td>
                        <td style="text-align: right;"><?php echo number_format($fund_stats['total_withdrawals']); ?> 원 (<?php echo number_format($fund_stats['new_contracts_count']); ?> 건)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

<?php include 'footer.php'; ?>
