<?php
include 'header.php';
require_once '../common.php';
global $link;

// 검색 파라미터 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if (isset($_GET['limit'])) {
    if ($_GET['limit'] === 'all') {
        $_SESSION['bond_ledger_limit'] = 'all';
    } else {
        $_SESSION['bond_ledger_limit'] = (int)$_GET['limit'];
    }
}
$limit = $_SESSION['bond_ledger_limit'] ?? 20;
$limit_options = [5, 10, 20, 50, 100, 200, 'all'];
$limit = in_array($limit, $limit_options) ? $limit : 20;

$search_params = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? 'valid', // 기본값: 유효(정상, 연체) 계약
];

// SQL 쿼리 구성
$sql = "SELECT 
            c.*, c.id as contract_id, 
            c.customer_id, 
            c.product_name, 
            c.loan_amount, 
            c.loan_date, 
            c.maturity_date, 
            c.agreement_date, 
            c.interest_rate, 
            c.overdue_interest_rate, 
            c.repayment_method,
            c.status,
            c.shortfall_amount,
            c.next_due_date,
            c.last_interest_calc_date,
            cu.name as customer_name,
            cu.resident_id_partial,
            cu.phone,
            cu.address_registered
        FROM contracts c
        JOIN customers cu ON c.customer_id = cu.id";

$where_clauses = [];
$params = [];
$types = '';

if ($search_params['status'] == 'valid') {
    $where_clauses[] = "c.status IN ('active', 'overdue')";
} elseif (!empty($search_params['status'])) {
    $where_clauses[] = "c.status = ?";
    $params[] = $search_params['status'];
    $types .= 's';
}

if (!empty($search_params['search'])) {
    $where_clauses[] = "(cu.name LIKE ? OR c.id LIKE ?)";
    $search_like = '%' . $search_params['search'] . '%';
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'ss';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY c.loan_date DESC, c.id DESC";

$stmt = mysqli_prepare($link, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$all_contracts = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// --- Pagination Calculations ---
$total_records = count($all_contracts);
if ($limit === 'all') {
    $contracts_for_page = $all_contracts;
    $total_pages = 1;
} else {
    $total_pages = ceil($total_records / $limit);
    $offset = ($page - 1) * $limit;
    $contracts_for_page = array_slice($all_contracts, $offset, $limit);
}

$today = new DateTime();
$today->setTime(0, 0, 0); // Compare dates only

// --- 요약 정보 계산 ---
// 필터와 상관없이 전체 계약 건수 조회
$total_contracts_query = mysqli_query($link, "SELECT COUNT(*) as total FROM contracts");
$total_contracts_count = mysqli_fetch_assoc($total_contracts_query)['total'] ?? 0;

// --- Summary Calculations ---
$summary = [
    'total_contracts' => $total_contracts_count, // 전체 계약 건수로 설정
    'active_contracts' => 0,
    'total_outstanding' => 0,
    'total_loan_amount' => 0,
    'normal_outstanding' => 0,
    'overdue_outstanding' => 0,
];

foreach ($all_contracts as $contract) { // Calculate summary on all filtered results
    $summary['total_loan_amount'] += (float)($contract['loan_amount'] ?? 0);

    $outstanding_principal = (float)($contract['current_outstanding_principal'] ?? 0);
    $summary['total_outstanding'] += $outstanding_principal;

    if ($contract['status'] == 'active' || $contract['status'] == 'overdue') {
        $summary['active_contracts']++;
        if ($contract['status'] == 'active') {
            $summary['normal_outstanding'] += $outstanding_principal;
        } elseif ($contract['status'] == 'overdue') {
            $summary['overdue_outstanding'] += $outstanding_principal;
        }
    }
}

// 연체율 계산
$summary['overdue_ratio'] = 0;
if ($summary['total_outstanding'] > 0) {
    $summary['overdue_ratio'] = round(($summary['overdue_outstanding'] / $summary['total_outstanding']) * 100, 2);
}

$statuses = ['valid' => '유효(정상,연체)', 'active' => '정상', 'overdue' => '연체', 'paid' => '완납', 'defaulted' => '부실'];

?>

<h2>채권원장</h2>

<!-- 검색 폼 -->
<div class="search-form-container">
    <form action="bond_ledger.php" method="get">
        <div class="search-form-flex">
            <div class="form-col">
                <label for="search">통합 검색</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_params['search']); ?>" placeholder="고객명, 계약번호">
            </div>
            <div class="form-col">
                <label for="status">계약 상태</label>
                <select id="status" name="status">
                    <?php foreach ($statuses as $key => $value): ?>
                        <option value="<?php echo $key; ?>" <?php if ($search_params['status'] == $key) echo 'selected'; ?>><?php echo $value; ?></option>
                    <?php endforeach; ?>
                    <option value="" <?php if ($search_params['status'] == '') echo 'selected'; ?>>전체</option>
                </select>
            </div>
            <div class="form-col">
                <label for="limit">표시 수</label>
                <select name="limit" id="limit" onchange="this.form.submit()">
                    <?php foreach ($limit_options as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php if ($limit == $opt) echo 'selected'; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-col">
                <button type="submit" class="btn btn-primary">검색</button>
                <a href="bond_ledger.php" class="btn btn-secondary">초기화</a>
            </div>
        </div>
    </form>
</div>
<div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
    <span>총 조회 건수: <?php echo number_format($total_records); ?>건</span>
</div>
<div class="page-action-buttons" style="display: flex; gap: 10px; margin-bottom: 20px;">
    <form action="../process/download_bond_ledger.php" method="get" style="display: inline;">
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_params['search']); ?>">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($search_params['status']); ?>">
        <button type="submit" class="btn btn-success">전체 다운로드</button>
    </form>
    <form action="../process/download_bond_ledger.php" method="get" style="display: inline;">
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_params['search']); ?>">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($search_params['status']); ?>">
        <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
        <input type="hidden" name="limit" value="<?php echo htmlspecialchars($limit); ?>">
        <button type="submit" class="btn btn-info">현재 페이지 다운로드</button>
    </form>
    <?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
    <button type="button" id="create_snapshot_btn" class="btn btn-info">원장스냅샷 생성</button>
    <?php endif; ?>
</div>



<!-- Summary Section -->
<div class="table-container">
    <table class="summary-table">
        <thead>
            <tr>
                <th>전체 채권 건수</th>
                <th>진행 채권 건수</th>
                <th>총 대출 합계</th>
                <th>현재 잔액 합계</th>
                <th>정상채권 잔액</th>
                <th>연체채권 잔액</th>
                <th>연체율</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php echo number_format($summary['total_contracts']); ?> 건</td>
                <td><?php echo number_format($summary['active_contracts']); ?> 건</td>
                <td><?php echo number_format($summary['total_loan_amount']); ?> 원</td>
                <td style="color: #007bff;"><?php echo number_format($summary['total_outstanding']); ?> 원</td>
                <td style="color: #28a745;"><?php echo number_format($summary['normal_outstanding']); ?> 원</td>
                <td style="color: #dc3545;"><?php echo number_format($summary['overdue_outstanding']); ?> 원</td>
                <td style="color: #dc3545;"><?php echo $summary['overdue_ratio']; ?>%</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- 데이터 테이블 -->
<div class="table-container">
    <table class="contract-list-table">
        <thead>
            <tr>
                <th>계약No</th>
                <th>성명</th>
                <th>상품명</th>
                <th>주민등록주소</th>
                <th>총대출</th>
                <th>계약일</th>
                <th>만기일</th>
                <th>약정일</th>
                <th>금리/연체금리</th>
                <th>핸드폰</th>
                <th>상환방법</th>
                <th>현재 잔액</th>
                <th>현재 연체일수</th>
                <th>현재 상태</th>
                <th>다음 상환일</th>
                <th>최근납입일</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contracts_for_page as $contract): ?>
                <tr>
                    <td><?php echo htmlspecialchars($contract['contract_id'] ?? ''); ?></td>
                    <td><a href="customer_detail.php?id=<?php echo $contract['customer_id']; ?>"><?php echo htmlspecialchars($contract['customer_name'] ?? ''); ?></a></td>
                    <td><?php echo htmlspecialchars($contract['product_name'] ?? ''); ?></td>
                    <td title="<?php echo htmlspecialchars($contract['address_registered'] ?? ''); ?>"><?php echo htmlspecialchars(mb_strimwidth($contract['address_registered'] ?? '', 0, 10, '...')); ?></td>
                    <td style="text-align: right;"><?php echo isset($contract['loan_amount']) ? number_format($contract['loan_amount']) : ''; ?></td>
                    <td><?php echo htmlspecialchars($contract['loan_date'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($contract['maturity_date'] ?? ''); ?></td>
                    <td><?php echo isset($contract['agreement_date']) ? htmlspecialchars($contract['agreement_date']) . '일' : ''; ?></td>
                    <td><?php echo (isset($contract['interest_rate']) && isset($contract['overdue_interest_rate'])) ? htmlspecialchars($contract['interest_rate'] . ' / ' . $contract['overdue_interest_rate']) . '%' : ''; ?></td>
                    <td><?php echo htmlspecialchars($contract['phone'] ?? ''); ?></td>
                    <td>
                        <?php 
                            $method = $contract['repayment_method'] ?? '';
                            if (empty($method) || strtolower($method) === 'bullet') {
                                echo '자유상환';
                            } else {
                                echo htmlspecialchars($method);
                            }
                        ?>
                    </td>
                    <td style="text-align: right; font-weight: bold;"><?php echo number_format($contract['current_outstanding_principal'] ?? 0); ?></td>
                    <td style="text-align: right; color: red;">
                        <?php
                            $overdue_days = 0;
                            if ($contract['status'] === 'overdue' && !empty($contract['next_due_date'])) {
                                $next_due_date = new DateTime($contract['next_due_date']);
                                if ($today > $next_due_date) {
                                    $overdue_days = $today->diff($next_due_date)->days;
                                }
                            }
                            echo $overdue_days > 0 ? $overdue_days . '일' : '';
                        ?>
                    </td>
                    <td><?php echo isset($contract['status']) ? get_status_display($contract['status']) : ''; ?></td>
                    <td><?php echo htmlspecialchars($contract['next_due_date'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($contract['last_interest_calc_date'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($contracts_for_page)): ?>
                <tr>
                    <td colspan="16" style="text-align: center;">데이터가 없습니다.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const createSnapshotBtn = document.getElementById('create_snapshot_btn');
    if (createSnapshotBtn) {
        createSnapshotBtn.addEventListener('click', function() {
            if (!confirm('현재 시점의 채권원장 스냅샷을 생성하시겠습니까? 이 작업은 시간이 걸릴 수 있습니다.')) {
                return;
            }

            this.disabled = true;
            this.textContent = '스냅샷 생성 중...';

            fetch('../process/create_ledger_snapshot.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.href = 'bond_ledger_history.php';
                } else {
                    alert('스냅샷 생성 실패: ' + data.message);
                    this.disabled = false;
                    this.textContent = '원장스냅샷 생성';
                }
            })
            .catch(error => {
                alert('오류가 발생했습니다. 개발자 콘솔을 확인해주세요. ' + error);
                this.disabled = false;
                this.textContent = '원장스냅샷 생성';
            });
        });
    }
});
</script>

<!-- Pagination -->
 <hr style="margin: 30px 0;">
<div class="pagination">
    <?php 
        $range = 5;
        $query_params = $_GET;
        unset($query_params['page']);
        $base_url = http_build_query($query_params);

        if ($total_pages > 1) {
            echo "<a href='?page=1&$base_url'>&laquo;</a> ";
            
            $prev_page = max(1, $page - 1);
            echo "<a href='?page=$prev_page&$base_url'>&lsaquo;</a> ";

            for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++) {
                echo ($i == $page) ? "<strong>$i</strong> " : "<a href='?page=$i&$base_url'>$i</a> ";
            }

            $next_page = min($total_pages, $page + 1);
            echo "<a href='?page=$next_page&$base_url'>&rsaquo;</a> ";

            echo "<a href='?page=$total_pages&$base_url'>&raquo;</a>";
        }
    ?>
</div>

<?php include 'footer.php'; ?>