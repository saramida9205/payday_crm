<?php
require_once __DIR__ . '/../common.php';
include_once 'header.php';

// --- Filtering & Pagination ---
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$customer_name_filter = $_GET['customer_name'] ?? '';
$phone_filter = $_GET['phone'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// --- Build WHERE clause for filtering ---
$where_clause = "WHERE 1=1";
$params = [];
$types = '';

if (!empty($start_date)) {
    $where_clause .= " AND DATE(request_time) >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if (!empty($end_date)) {
    $where_clause .= " AND DATE(request_time) <= ?";
    $params[] = $end_date;
    $types .= 's';
}
if (!empty($status_filter)) {
    $where_clause .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if (!empty($customer_name_filter)) {
    $where_clause .= " AND customer_name LIKE ?";
    $params[] = '%' . $customer_name_filter . '%';
    $types .= 's';
}
if (!empty($phone_filter)) {
    $where_clause .= " AND recipient_phone LIKE ?";
    $params[] = '%' . $phone_filter . '%';
    $types .= 's';
}

// --- Summary Stats ---
$summary_sql = "SELECT status, COUNT(*) as count FROM sms_log " . $where_clause . " GROUP BY status";
$summary_stmt = mysqli_prepare($link, $summary_sql);
if ($summary_stmt && !empty($params)) {
    mysqli_stmt_bind_param($summary_stmt, $types, ...$params);
}
mysqli_stmt_execute($summary_stmt);
$summary_result = mysqli_stmt_get_result($summary_stmt);
$summary_stats = ['total' => 0, 'pending' => 0, 'sent' => 0, 'failed' => 0];
while ($row = mysqli_fetch_assoc($summary_result)) {
    if (isset($summary_stats[$row['status']])) {
        $summary_stats[$row['status']] = $row['count'];
    }
    $summary_stats['total'] += $row['count'];
}
mysqli_stmt_close($summary_stmt);

// --- Total Records for Pagination ---
$total_sql = "SELECT COUNT(*) FROM sms_log " . $where_clause;
$total_stmt = mysqli_prepare($link, $total_sql);
if ($total_stmt && !empty($params)) {
    mysqli_stmt_bind_param($total_stmt, $types, ...$params);
}
mysqli_stmt_execute($total_stmt);
$total_records = mysqli_fetch_array(mysqli_stmt_get_result($total_stmt))[0];
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($total_stmt);

// --- Data Fetching for current page ---
$sql = "SELECT * FROM sms_log " . $where_clause . " ORDER BY id DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= 'ii';

$stmt = mysqli_prepare($link, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$logs = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

?>

<style>
    .summary-card { text-align: center; padding: 15px; border-radius: 5px; color: white; }
    .summary-card h4 { margin: 0; font-size: 24px; font-weight: bold; }
    .summary-card p { margin: 0; font-size: 14px; }
    .bg-total { background-color: #17a2b8; }
    .bg-pending { background-color: #ffc107; color: #212529 !important; }
    .bg-sent { background-color: #28a745; }
    .bg-failed { background-color: #dc3545; }

    .table-responsive {
        /* max-height: 65vh; */ /* 테이블 높이 제한을 제거하여 페이지 전체 스크롤을 사용합니다. */
    }
    .table th {
        position: sticky;
        top: 0;
        background-color: #f8f9fa;
        z-index: 1;
        vertical-align: middle;
    }
    .message-content {
        max-width: 250px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
    }
    .result-msg {
        max-width: 150px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: help;
    }
    .summary-container {
        display: flex;
        gap: 15px;
    }
</style>

<h2><i class="fas fa-history"></i> SMS 발송 내역</h2>

<div class="card">
    <div class="card-body">
        <form method="get" class="search-form-flex">
            <div class="form-col">
                <label>조회기간</label>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                    <span>~</span>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
            </div>
            <div class="form-col">
                <label for="status">상태</label>
                <select id="status" name="status">
                    <option value="">전체</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>결과대기</option>
                    <option value="sent" <?php echo $status_filter == 'sent' ? 'selected' : ''; ?>>발송성공</option>
                    <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>발송실패</option>
                </select>
            </div>
            <div class="form-col">
                <label for="customer_name">고객명</label>
                <input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($customer_name_filter); ?>" placeholder="고객명으로 검색">
            </div>
            <div class="form-col">
                <label for="phone">연락처</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone_filter); ?>" placeholder="연락처로 검색">
            </div>
            <div class="form-col">
                <button type="submit" class="btn btn-primary">조회</button>
                <?php if ($summary_stats['pending'] > 0): ?>
                    <button type="button" id="check-all-pending-btn" class="btn btn-warning">대기중인 모든 건 결과 확인 (<?php echo $summary_stats['pending']; ?>건)</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="summary-container my-3">
    <div class="summary-card bg-total" style="flex:1;">
        <div class="summary-card bg-total">
            <h4><?php echo number_format($summary_stats['total']); ?></h4>
            <p>전체</p>
        </div>
    </div>
    <div class="summary-card bg-pending" style="flex:1;">
        <div class="summary-card bg-pending">
            <h4><?php echo number_format($summary_stats['pending']); ?></h4>
            <p>결과대기</p>
        </div>
    </div>
    <div class="summary-card bg-sent" style="flex:1;">
        <div class="summary-card bg-sent">
            <h4><?php echo number_format($summary_stats['sent']); ?></h4>
            <p>발송성공</p>
        </div>
    </div>
    <div class="summary-card bg-failed" style="flex:1;">
        <div class="summary-card bg-failed">
            <h4><?php echo number_format($summary_stats['failed']); ?></h4>
            <p>발송실패</p>
        </div>
    </div>
</div>

<div class="table-responsive mt-3">
    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>요청시간</th>
                <th>상태</th>
                <th>고객명</th>
                <th>수신번호</th>
                <th style="width: 30%;">내용</th>
                <th>요청결과</th>
                <th>최종결과</th>
                <th style="width: 100px;">작업</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="9" class="text-center">조회된 발송 내역이 없습니다.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
            <tr id="log-row-<?php echo $log['id']; ?>">
                <td><?php echo $log['id']; ?></td>
                <td><?php echo $log['request_time']; ?></td>
                <td class="log-status"><?php echo getStatusBadge($log['status']); ?></td>
                <td><?php echo htmlspecialchars($log['customer_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($log['recipient_phone']); ?></td>
                <td class="message-content" title="<?php echo htmlspecialchars($log['message_content']); ?>"><?php echo htmlspecialchars($log['message_content']); ?></td>
                <td class="result-msg" title="<?php echo htmlspecialchars($log['api_request_result_msg']); ?>"><?php echo htmlspecialchars($log['api_request_result_msg']); ?></td>
                <td class="log-final-result result-msg" title="<?php echo htmlspecialchars($log['final_result_msg'] ?? '확인 전'); ?>"><?php echo htmlspecialchars($log['final_result_msg'] ?? '확인 전'); ?></td>
                <td class="text-center">
                    <?php if ($log['status'] === 'pending'): ?>
                        <button class="btn btn-sm btn-info check-result-btn" data-log-id="<?php echo $log['id']; ?>" data-userkey="<?php echo htmlspecialchars($log['userkey']); ?>">결과확인</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.message-content').forEach(function(element) {
        element.addEventListener('click', function() {
            alert(this.getAttribute('title'));
        });
    });

    document.querySelectorAll('.check-result-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const logId = this.dataset.logId;
            const userKey = this.dataset.userkey;
            const row = document.getElementById('log-row-' + logId);
            const statusCell = row.querySelector('.log-status');
            const resultCell = row.querySelector('.log-final-result');

            this.disabled = true;
            this.textContent = '확인중...';

            fetch('../process/sms_result_checker.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=check_single&log_id=${logId}&userkey=${userKey}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusCell.innerHTML = data.status_badge;
                    resultCell.textContent = data.final_result_msg;
                    resultCell.title = data.final_result_msg;
                    this.remove(); // 성공 시 버튼 제거
                } else {
                    alert('결과 확인 실패: ' + data.message);
                    this.disabled = false;
                    this.textContent = '결과확인';
                }
            })
            .catch(error => {
                alert('오류가 발생했습니다: ' + error);
                this.disabled = false;
                this.textContent = '결과확인';
            });
        });
    });

    const checkAllPendingBtn = document.getElementById('check-all-pending-btn');
    if (checkAllPendingBtn) {
        checkAllPendingBtn.addEventListener('click', function() {
            if (!confirm('결과가 "대기" 상태인 모든 발송 건의 최종 결과를 조회하시겠습니까? 건수에 따라 시간이 소요될 수 있습니다.')) {
                return;
            }

            this.disabled = true;
            this.textContent = '전체 결과 확인 중...';

            fetch('../process/sms_result_checker.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=check_pending_bulk'
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                window.location.reload(); // 페이지를 새로고침하여 업데이트된 상태를 보여줍니다.
            })
            .catch(error => {
                alert('오류가 발생했습니다: ' + error);
                this.disabled = false;
                this.textContent = '대기중인 모든 건 결과 확인';
            });
        });
    }
});
</script>

<?php include_once 'footer.php'; ?>