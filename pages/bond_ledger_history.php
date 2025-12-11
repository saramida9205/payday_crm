<?php
include 'header.php';
require_once '../common.php';
global $link;

// 사용 가능한 스냅샷 날짜 목록 가져오기
$snapshot_dates_query = mysqli_query($link, "SELECT DISTINCT snapshot_date FROM bond_ledger_snapshots ORDER BY snapshot_date DESC");
$snapshot_dates = mysqli_fetch_all($snapshot_dates_query, MYSQLI_ASSOC);

// --- Pagination and Search Setup ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if (isset($_GET['limit'])) {
    if ($_GET['limit'] === 'all') {
        $_SESSION['bond_ledger_history_limit'] = 'all';
    } else {
        $_SESSION['bond_ledger_history_limit'] = (int)$_GET['limit'];
    }
}
$limit = $_SESSION['bond_ledger_history_limit'] ?? 20;
$limit_options = [5, 10, 20, 50, 100, 200, 'all'];
$limit = in_array($limit, $limit_options) ? $limit : 20;

$selected_date = $_GET['snapshot_date'] ?? ($snapshot_dates[0]['snapshot_date'] ?? '');
$search_term = $_GET['search'] ?? '';

$all_snapshots = [];
if (!empty($selected_date)) {
    $sql = "SELECT * FROM bond_ledger_snapshots WHERE snapshot_date = ?";
    $params = [$selected_date];
    $types = 's';

    if (!empty($search_term)) {
        $sql .= " AND (customer_name LIKE ? OR contract_id LIKE ?)";
        $search_like = '%' . $search_term . '%';
        $params[] = $search_like;
        $params[] = $search_like;
        $types .= 'ss';
    }

    $sql .= " ORDER BY loan_date DESC";

    $stmt = mysqli_prepare($link, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $all_snapshots = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// --- Pagination Calculations ---
$total_records = count($all_snapshots);
if ($limit === 'all') {
    $snapshots_for_page = $all_snapshots;
    $total_pages = 1;
} else {
    $total_pages = ceil($total_records / $limit);
    $offset = ($page - 1) * $limit;
    $snapshots_for_page = array_slice($all_snapshots, $offset, $limit);
}
?>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: #fefefe; margin: 10% auto; padding: 25px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.close-button { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
.snapshot-list { max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 20px; }
.snapshot-item { display: block; padding: 8px; border-bottom: 1px solid #eee; }
</style>

<h2>과거 채권원장 조회</h2>

<!-- 검색 폼 -->
<div class="search-form-container">
    <form action="bond_ledger_history.php" method="get">
        <div class="search-form-flex">
            <div class="form-col">
                <label for="snapshot_date">조회 기준</label>
                <select id="snapshot_date" name="snapshot_date">
                    <option value="">-- 날짜와 시간 선택 --</option>
                    <?php foreach ($snapshot_dates as $date_row): ?>
                        <option value="<?php echo $date_row['snapshot_date']; ?>" <?php if ($selected_date == $date_row['snapshot_date']) echo 'selected'; ?>>
                            <?php echo $date_row['snapshot_date']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-col">
                <label for="search">통합 검색</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="고객명, 계약번호">
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
                <a href="bond_ledger_history.php" class="btn btn-secondary">초기화</a>
                <?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
                <button type="button" id="manage-snapshots-btn" class="btn btn-info">스냅샷 관리</button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($selected_date)): ?>
    <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
        <span><strong><?php echo $selected_date; ?></strong> 기준 총 <strong><?php echo number_format($total_records); ?></strong>건의 데이터가 조회되었습니다.</span>
        <div class="page-action-buttons" style="display: flex; gap: 10px;">
            <form action="../process/download_bond_ledger_history.php" method="get" style="display: inline;" id="download-all-form">
                <input type="hidden" name="snapshot_date" value="<?php echo htmlspecialchars($selected_date); ?>">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-success">전체 다운로드</button>
            </form>
            <form action="../process/download_bond_ledger_history.php" method="get" style="display: inline;" id="download-page-form">
                <input type="hidden" name="snapshot_date" value="<?php echo htmlspecialchars($selected_date); ?>">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
                <input type="hidden" name="limit" value="<?php echo htmlspecialchars($limit); ?>">
                <button type="submit" class="btn btn-info">현재 페이지 다운로드</button>
            </form>
        </div>
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
                    <th>당시 잔액</th>
                    <th>당시 연체일수</th>
                    <th>당시 상태</th>
                    <th>당시 다음상환일</th>
                    <th>당시 최근납입일</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($snapshots_for_page as $snapshot): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($snapshot['contract_id'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($snapshot['customer_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($snapshot['product_name'] ?? ''); ?></td>
                        <td title="<?php echo htmlspecialchars($snapshot['address_registered'] ?? ''); ?>"><?php echo htmlspecialchars(mb_strimwidth($snapshot['address_registered'] ?? '', 0, 10, '...')); ?></td>
                        <td style="text-align: right;"><?php echo number_format($snapshot['loan_amount'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars($snapshot['loan_date'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($snapshot['maturity_date'] ?? ''); ?></td>
                        <td><?php echo isset($snapshot['agreement_date']) ? htmlspecialchars($snapshot['agreement_date']) . '일' : ''; ?></td>
                        <td><?php echo htmlspecialchars(($snapshot['interest_rate'] ?? '') . ' / ' . ($snapshot['overdue_interest_rate'] ?? '')); ?>%</td>
                        <td><?php echo htmlspecialchars($snapshot['customer_phone'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($snapshot['repayment_method'] ?? ''); ?></td>
                        <td style="text-align: right; font-weight: bold;"><?php echo number_format($snapshot['outstanding_principal'] ?? 0); ?></td>
                        <td style="text-align: right; color: red;"><?php echo ($snapshot['overdue_days'] ?? 0) > 0 ? $snapshot['overdue_days'] . '일' : ''; ?></td>
                        <td><?php echo get_status_display($snapshot['status'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($snapshot['next_due_date'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($snapshot['last_interest_calc_date'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($snapshots_for_page)): ?>
                    <tr>
                        <td colspan="16" style="text-align: center;">데이터가 없습니다.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

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

<!-- Snapshot Management Modal -->
<div id="snapshot-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>스냅샷 관리</h3>
            <span class="close-button">&times;</span>
        </div>
        <form id="delete-snapshots-form">
            <div class="snapshot-list">
                <?php if (empty($snapshot_dates)): ?>
                    <p>생성된 스냅샷이 없습니다.</p>
                <?php else: ?>
                    <label class="snapshot-item">
                        <input type="checkbox" id="select-all-snapshots"> <strong>전체 선택</strong>
                    </label>
                    <?php foreach ($snapshot_dates as $date_row): ?>
                        <label class="snapshot-item">
                            <input type="checkbox" name="snapshot_dates[]" value="<?php echo htmlspecialchars($date_row['snapshot_date']); ?>" class="snapshot-checkbox">
                            <?php echo htmlspecialchars($date_row['snapshot_date']); ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="form-buttons" style="text-align: right;">
                <button type="submit" class="btn btn-danger">선택한 스냅샷 삭제</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('snapshot-modal');
    const manageBtn = document.getElementById('manage-snapshots-btn');
    const closeBtn = modal.querySelector('.close-button');
    const selectAllCheckbox = document.getElementById('select-all-snapshots');
    const snapshotCheckboxes = document.querySelectorAll('.snapshot-checkbox');
    const deleteForm = document.getElementById('delete-snapshots-form');

    if (manageBtn) {
        manageBtn.addEventListener('click', () => {
            modal.style.display = 'block';
        });
    }

    closeBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });

    selectAllCheckbox.addEventListener('change', function() {
        snapshotCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    deleteForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const selectedCount = formData.getAll('snapshot_dates[]').length;

        if (selectedCount === 0) {
            alert('삭제할 스냅샷을 하나 이상 선택해주세요.');
            return;
        }

        if (confirm(`선택한 ${selectedCount}개의 스냅샷을 정말로 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.`)) {
            formData.append('action', 'delete_snapshots');
            fetch('../process/delete_ledger_snapshot.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => alert('오류가 발생했습니다: ' + error));
        }
    });
});
</script>

<?php include 'footer.php'; ?>