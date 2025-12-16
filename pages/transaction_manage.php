<?php
include('../process/transaction_process.php');
include('header.php');

$active_tab = $_GET['tab'] ?? 'deposits';

// Withdrawal Details Filters
if (isset($_GET['wd_start_date']) || isset($_GET['wd_end_date']) || isset($_GET['wd_customer_name']) || isset($_GET['wd_sort_order'])) {
    $wd_start_date = $_GET['wd_start_date'] ?? '';
    $wd_end_date = $_GET['wd_end_date'] ?? '';
} else {
    $wd_start_date = date('Y-m-d');
    $wd_end_date = date('Y-m-d');
}
$wd_customer_name = $_GET['wd_customer_name'] ?? '';
$wd_sort_order = $_GET['wd_sort_order'] ?? 'desc';

// Deposit Details Filters
if (isset($_GET['dp_start_date']) || isset($_GET['dp_end_date']) || isset($_GET['dp_customer_name']) || isset($_GET['dp_sort_order'])) {
    $dp_start_date = $_GET['dp_start_date'] ?? '';
    $dp_end_date = $_GET['dp_end_date'] ?? '';
} else {
    $dp_start_date = date('Y-m-d');
    $dp_end_date = date('Y-m-d');
}
$dp_customer_name = $_GET['dp_customer_name'] ?? '';
$dp_sort_order = $_GET['dp_sort_order'] ?? 'desc';

$withdrawal_details = [];
$deposit_details = [];
$withdrawal_totals = ['count' => 0, 'loan_amount' => 0, 'balance_as_of' => 0];
$deposit_totals = ['count' => 0, 'total_deposit' => 0, 'expense' => 0, 'interest' => 0, 'principal' => 0];

if ($active_tab == 'withdrawals') {
    $withdrawal_details = getWithdrawalDetails($link, $wd_start_date, $wd_end_date, $wd_customer_name, $wd_sort_order);
    foreach ($withdrawal_details as $row) {
        $withdrawal_totals['count']++;
        $withdrawal_totals['loan_amount'] += $row['loan_amount'];
        $withdrawal_totals['balance_as_of'] += $row['balance_as_of'];
    }
} elseif ($active_tab == 'deposits') {
    $deposit_details = getDepositDetails($link, $dp_start_date, $dp_end_date, $dp_customer_name, $dp_sort_order);
    foreach ($deposit_details as $row) {
        $deposit_totals['count']++;
        $deposit_totals['total_deposit'] += $row['total_deposit'];
        $deposit_totals['expense'] += $row['expense'];
        $deposit_totals['interest'] += $row['interest'];
        $deposit_totals['principal'] += $row['principal'];
    }
}
?>

<style>
    .tab-nav {
        border-bottom: 2px solid #dee2e6;
        margin-bottom: 20px;
    }

    .tab-nav a {
        display: inline-block;
        padding: 10px 15px;
        border: 1px solid transparent;
        border-bottom: 0;
        margin-bottom: -2px;
        text-decoration: none;
        color: #495057;
    }

    .tab-nav a.active {
        color: #007bff;
        border-color: #dee2e6 #dee2e6 #fff;
        border-radius: 0.25rem 0.25rem 0 0;
        font-weight: bold;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .clickable-row {
        cursor: pointer;
    }

    .clickable-row:hover {
        background-color: #f8f9fa;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 25px;
        border: 1px solid #888;
        width: 80%;
        max-width: 700px;
        border-radius: 8px;
    }

    .close-button {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
    }

    .close-button:hover,
    .close-button:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }
</style>

<h2>입출금 관리</h2>

<div class="tab-nav">
    <a href="?tab=deposits" class="<?php echo $active_tab == 'deposits' ? 'active' : ''; ?>">입금 명세</a>
    <a href="?tab=withdrawals" class="<?php echo $active_tab == 'withdrawals' ? 'active' : ''; ?>">출금 명세</a>
</div>


<!-- 입금 명세 탭 -->
<div id="deposits" class="tab-content <?php echo $active_tab == 'deposits' ? 'active' : ''; ?>">
    <h3>입금 명세</h3>

    <!-- Search Form -->
    <form action="transaction_manage.php" method="get" class="form-container">
        <input type="hidden" name="tab" value="deposits">
        <div class="search-form-flex">
            <div class="form-col"><label>입금일 (시작)</label><input type="date" name="dp_start_date" value="<?php echo htmlspecialchars($dp_start_date); ?>"></div>
            <div class="form-col"><label>입금일 (종료)</label><input type="date" name="dp_end_date" value="<?php echo htmlspecialchars($dp_end_date); ?>"></div>
            <div class="form-col"><label>고객명</label><input type="text" name="dp_customer_name" placeholder="고객명" value="<?php echo htmlspecialchars($dp_customer_name); ?>"></div>
            <div class="form-col"><label>정렬</label>
                <select name="dp_sort_order">
                    <option value="desc" <?php if ($dp_sort_order == 'desc') echo 'selected'; ?>>최근 날짜순</option>
                    <option value="asc" <?php if ($dp_sort_order == 'asc') echo 'selected'; ?>>오래된 날짜순</option>
                </select>
            </div>
        </div>
        <div class="search-form-buttons">
            <button type="submit" class="btn btn-primary">조회</button>
            <a href="transaction_manage.php?tab=deposits" class="btn btn-secondary">초기화 (오늘)</a>
            <a href="transaction_manage.php?tab=deposits&dp_start_date=&dp_end_date=" class="btn btn-info">전체조회</a>
        </div>
    </form>

    <div class="page-action-buttons">
        <form action="../process/download_deposits.php" method="get" style="display: inline;">
            <input type="hidden" name="dp_start_date" value="<?php echo htmlspecialchars($dp_start_date); ?>">
            <input type="hidden" name="dp_end_date" value="<?php echo htmlspecialchars($dp_end_date); ?>">
            <input type="hidden" name="dp_customer_name" value="<?php echo htmlspecialchars($dp_customer_name); ?>">
            <input type="hidden" name="dp_sort_order" value="<?php echo htmlspecialchars($dp_sort_order); ?>">
            <button type="submit" class="btn btn-success">엑셀 다운로드</button>
        </form>
    </div>

    <!-- Results Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>회원번호</th>
                    <th>계약번호</th>
                    <th>고객명</th>
                    <th>이전입금일</th>
                    <th>현재입금일</th>
                    <th>약정일</th>
                    <th>입금액</th>
                    <th>비용상환</th>
                    <th>이자상환</th>
                    <th>원금상환</th>
                    <th>부족금액</th>
                    <th>현재잔액</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deposit_details)): ?>
                    <tr>
                        <td colspan="12" style="text-align: center;">조회된 내역이 없습니다.</td>
                    </tr>
                    <?php else: foreach ($deposit_details as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['customer_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['contract_id']); ?></td>
                            <td><a href="customer_detail.php?id=<?php echo $row['customer_id']; ?>"><?php echo htmlspecialchars($row['customer_name']); ?></a></td>
                            <td style="font-weight: light"><?php echo htmlspecialchars($row['previous_deposit_date']); ?></td>
                            <td style="font-weight: bold"><?php echo htmlspecialchars($row['collection_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['agreement_date']); ?>일</td>
                            <td style="text-align: right; font-weight: bold;"><?php echo number_format($row['total_deposit']); ?></td>
                            <td style="text-align: right;"><?php echo number_format($row['expense']); ?></td>
                            <td style="text-align: right;"><?php echo number_format($row['interest']); ?></td>
                            <td style="text-align: right;"><?php echo number_format($row['principal']); ?></td>
                            <td style="text-align: right; color: #dc3545; font-weight: bold; "><?php echo number_format($row['shortfall']); ?></td>
                            <td style="text-align: right; font-weight: bold;"><?php echo number_format($row['balance_as_of']); ?></td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>합계</strong></td>
                    <td colspan="3"><strong>총 <?php echo number_format($deposit_totals['count']); ?> 건</strong></td>
                    <td style="text-align: right;"><strong><?php echo number_format($deposit_totals['total_deposit']); ?></strong></td>
                    <td style="text-align: right;"><strong><?php echo number_format($deposit_totals['expense']); ?></strong></td>
                    <td style="text-align: right;"><strong><?php echo number_format($deposit_totals['interest']); ?></strong></td>
                    <td style="text-align: right;"><strong><?php echo number_format($deposit_totals['principal']); ?></strong></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>


<!-- 출금 명세 탭 -->
<div id="withdrawals" class="tab-content <?php echo $active_tab == 'withdrawals' ? 'active' : ''; ?>">
    <h3>출금 명세 (대출금 지급)</h3>

    <!-- Search Form -->
    <form action="transaction_manage.php" method="get" class="form-container">
        <input type="hidden" name="tab" value="withdrawals">
        <div class="search-form-flex">
            <div class="form-col"><label>출금일 (시작)</label><input type="date" name="wd_start_date" value="<?php echo htmlspecialchars($wd_start_date); ?>"></div>
            <div class="form-col"><label>출금일 (종료)</label><input type="date" name="wd_end_date" value="<?php echo htmlspecialchars($wd_end_date); ?>"></div>
            <div class="form-col"><label>고객명</label><input type="text" name="wd_customer_name" placeholder="고객명" value="<?php echo htmlspecialchars($wd_customer_name); ?>"></div>
            <div class="form-col"><label>정렬</label>
                <select name="wd_sort_order">
                    <option value="desc" <?php if ($wd_sort_order == 'desc') echo 'selected'; ?>>최근 날짜순</option>
                    <option value="asc" <?php if ($wd_sort_order == 'asc') echo 'selected'; ?>>오래된 날짜순</option>
                </select>
            </div>
        </div>
        <div class="search-form-buttons">
            <button type="submit" class="btn btn-primary">조회</button>
            <a href="transaction_manage.php?tab=withdrawals" class="btn btn-secondary">초기화 (오늘)</a>
            <a href="transaction_manage.php?tab=withdrawals&wd_start_date=&wd_end_date=" class="btn btn-info">전체조회</a>
        </div>
    </form>

    <div class="page-action-buttons">
        <form action="../process/download_withdrawals.php" method="get" style="display: inline;">
            <input type="hidden" name="wd_start_date" value="<?php echo htmlspecialchars($wd_start_date); ?>">
            <input type="hidden" name="wd_end_date" value="<?php echo htmlspecialchars($wd_end_date); ?>">
            <input type="hidden" name="wd_customer_name" value="<?php echo htmlspecialchars($wd_customer_name); ?>">
            <input type="hidden" name="wd_sort_order" value="<?php echo htmlspecialchars($wd_sort_order); ?>">
            <button type="submit" class="btn btn-success">엑셀 다운로드</button>
        </form>
    </div>

    <!-- Results Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>회원번호</th>
                    <th>계약번호</th>
                    <th>현재상태</th>
                    <th>상품명</th>
                    <th>고객명</th>
                    <th>계약일</th>
                    <th>만기일</th>
                    <th>약정일</th>
                    <th>차기상환일</th>
                    <th>이율</th>
                    <th>출금금액</th>
                    <th>송금계좌</th>
                    <th>조회기준일 잔액</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($withdrawal_details)): ?>
                    <tr>
                        <td colspan="13" style="text-align: center;">조회된 내역이 없습니다.</td>
                    </tr>
                    <?php else:
                    foreach ($withdrawal_details as $row):
                        $row_data_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr class="clickable-row" data-contract-info='<?php echo $row_data_json; ?>'>
                            <td><?php echo htmlspecialchars($row['customer_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['contract_id']); ?></td>
                            <td><?php echo get_status_display($row['status']); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td><a href="customer_detail.php?id=<?php echo $row['customer_id']; ?>"><?php echo htmlspecialchars($row['customer_name']); ?></a></td>
                            <td><?php echo htmlspecialchars($row['loan_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['maturity_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['agreement_date']); ?>일</td>
                            <td><?php echo htmlspecialchars($row['next_due_date'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['interest_rate']); ?>%</td>
                            <td style="text-align: right;"><?php echo number_format($row['loan_amount']); ?></td>
                            <td><?php echo htmlspecialchars($row['bank_name'] . ' ' . $row['account_number']); ?></td>
                            <td style="text-align: right; font-weight: bold;"><?php echo number_format($row['balance_as_of']); ?></td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>합계</strong></td>
                    <td colspan="3"><strong>총 <?php echo number_format($withdrawal_totals['count']); ?> 건</strong></td>
                    <td colspan="4"></td>
                    <td style="text-align: right;"><strong><?php echo number_format($withdrawal_totals['loan_amount']); ?></strong></td>
                    <td></td>
                    <td style="text-align: right;"><strong><?php echo number_format($withdrawal_totals['balance_as_of']); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>


<!-- Contract Detail Modal -->
<div id="contractDetailModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h3>계약 상세 정보</h3>
        <div class="info-section-container" style="display: flex; gap: 20px;">
            <div style="flex: 1;">
                <h4>기본 정보</h4>
                <div class="info-grid-condensed">
                    <div class="info-item"><strong>계약번호:</strong><span id="modal_contract_id"></span></div>
                    <div class="info-item"><strong>고객명:</strong><span id="modal_customer_name"></span></div>
                    <div class="info-item"><strong>대출상품명:</strong><span id="modal_product_name"></span></div>
                    <div class="info-item"><strong>최초대출금액:</strong><span id="modal_loan_amount"></span></div>
                    <div class="info-item"><strong>계약일:</strong><span id="modal_loan_date"></span></div>
                    <div class="info-item"><strong>만기일:</strong><span id="modal_maturity_date"></span></div>
                    <div class="info-item"><strong>약정일:</strong><span id="modal_agreement_date"></span></div>
                    <div class="info-item"><strong>대출금리:</strong><span id="modal_interest_rate"></span></div>
                </div>
            </div>
            <div style="flex: 1;">
                <h4>실시간 채권 상태 (오늘 기준)</h4>
                <div class="info-grid-condensed">
                    <div class="info-item"><strong>대출잔액:</strong><span id="modal_balance_as_of" class="highlight-blue"></span></div>
                    <div class="info-item"><strong>미수/부족금:</strong><span id="modal_shortfall_amount"></span></div>
                    <div class="info-item"><strong>마지막 거래일:</strong><span id="modal_last_collection_date"></span></div>
                    <div class="info-item"><strong>다음 약정일:</strong><span id="modal_next_due_date"></span></div>
                </div>
            </div>
        </div>
        <div style="text-align: right; margin-top: 20px;">
            <a id="modal_ledger_link" href="#" class="btn btn-primary">입출금 원장 보기</a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('contractDetailModal');
        const closeBtn = modal.querySelector('.close-button');

        document.querySelectorAll('.clickable-row').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.tagName === 'A') return; // Do not trigger modal if a link inside the row is clicked

                const info = JSON.parse(this.dataset.contractInfo);

                document.getElementById('modal_contract_id').textContent = info.contract_id;
                document.getElementById('modal_customer_name').textContent = info.customer_name;
                document.getElementById('modal_product_name').textContent = info.product_name;
                document.getElementById('modal_loan_amount').textContent = Number(info.loan_amount).toLocaleString() + ' 원';
                document.getElementById('modal_loan_date').textContent = info.loan_date;
                document.getElementById('modal_maturity_date').textContent = info.maturity_date;
                document.getElementById('modal_agreement_date').textContent = `매월 ${info.agreement_date}일`;
                document.getElementById('modal_interest_rate').textContent = `연 ${info.interest_rate}% / ${info.overdue_interest_rate}%`;
                document.getElementById('modal_balance_as_of').textContent = Number(info.balance_as_of).toLocaleString() + ' 원';
                document.getElementById('modal_shortfall_amount').textContent = Number(info.shortfall_amount).toLocaleString() + ' 원';
                document.getElementById('modal_last_collection_date').textContent = info.last_collection_date || '-';
                document.getElementById('modal_next_due_date').textContent = info.next_due_date || '-';
                document.getElementById('modal_ledger_link').href = `transaction_ledger.php?contract_id=${info.contract_id}`;

                modal.style.display = 'block';
            });
        });

        closeBtn.onclick = () => {
            modal.style.display = 'none';
        };
        window.onclick = (event) => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        };
    });
</script>

<?php include 'footer.php'; ?>