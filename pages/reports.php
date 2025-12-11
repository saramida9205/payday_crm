<?php
include('../process/report_process.php');
include('header.php');

// Set default dates if not provided
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Fetch statistics based on the date range
$stats = getOverallStats($link, $start_date, $end_date);
?>

<h2>보고서</h2>

<form action="reports.php" method="get" class="form-container">
    <div class="search-form-flex" style="margin-bottom: 15px; justify-content: center;">
        <div class="form-col"><label>조회 시작일</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="form-col"><label>조회 종료일</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <div class="form-col">
            <button type="submit" class="btn btn-primary" style="align-self: flex-end;">조회</button>
        </div>
    </div>
</form>

<div class="box">
    <div class="report-container">
        <h3>대출 현황 (조회 종료일: <?php echo htmlspecialchars($end_date); ?> 기준)</h3>
        <hr style="margin: 15px 0;">
        <div class="report-item">
            <span>총 대출 원금 (진행중):</span>
            <strong><?php echo number_format($stats['total_loan_amount'] ?? 0); ?> 원</strong>
        </div>
        <div class="report-item">
            <span>총 회수 원금:</span>
            <strong><?php echo number_format($stats['total_principal_collected'] ?? 0); ?> 원</strong>
        </div>
        <div class="report-item">
            <span>총 대출 잔액:</span>
            <strong style="color: red;"><?php echo number_format($stats['total_outstanding_balance'] ?? 0); ?> 원</strong>
        </div>
        <div class="report-item">
            <span>총 회수 이자:</span>
            <strong style="color: blue;"><?php echo number_format($stats['total_interest_collected'] ?? 0); ?> 원</strong>
        </div>
        <div class="report-item">
            <span>진행중인 계약 건수:</span>
            <strong><?php echo number_format($stats['active_contracts_count']); ?> 건</strong>
        </div>
        <div class="report-item">
            <span>총 고객 수:</span>
            <strong><?php echo number_format($stats['total_customers_count'] ?? 0); ?> 명</strong>
        </div>
    </div>

    <div class="report-container">
        <h3>기간 내 자금 현황 (<?php echo htmlspecialchars($start_date); ?> ~ <?php echo htmlspecialchars($end_date); ?>)</h3>
        <hr style="margin: 15px 0;">
        <div class="report-item">
            <span>기간 내 회수 원금:</span>
            <strong><?php echo number_format($stats['principal_collected_period'] ?? 0); ?> 원</strong>
        </div>
        <div class="report-item">
            <span>기간 내 회수 이자:</span>
            <strong><?php echo number_format($stats['interest_collected_period'] ?? 0); ?> 원</strong>
        </div>
        <div class="report-item">
            <span>기간 내 신규 계약:</span>
            <strong>
                <?php echo number_format($stats['new_contracts_count_period'] ?? 0); ?> 건 / <?php echo number_format($stats['new_contracts_amount_period'] ?? 0); ?> 원
            </strong>
        </div>
        <div class="report-item">
            <span>총 입금액:</span>
            <strong style="color: blue;"><?php echo number_format($stats['total_deposits'] ?? 0); ?> 원</strong>
        </div>
        <div class="report-item">
            <span style="padding-left: 20px;">- 비용 입금:</span>
            <strong><?php echo number_format($stats['expense_deposits_period'] ?? 0); ?> 원</strong>
        </div>
        <div class="report-item">
            <span style="padding-left: 20px;">- 이자 입금:</span>
            <strong><?php echo number_format($stats['interest_collected_period'] ?? 0); ?> 원</strong>
        </div>
        <div class="report-item">
            <span style="padding-left: 20px;">- 원금 입금:</span>
            <strong><?php echo number_format($stats['principal_collected_period'] ?? 0); ?> 원</strong>
        </div>
        <div class="report-item">
            <span>총 출금액:</span>
            <strong style="color: red;"><?php echo number_format($stats['total_withdrawals'] ?? 0); ?> 원</strong>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>