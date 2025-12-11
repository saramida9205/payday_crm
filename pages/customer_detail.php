<?php 
include('header.php'); 
include_once(__DIR__ . '/../common.php');
include_once('../process/file_process.php'); // 파일 처리 로직 포함

if(!isset($_GET['id']) || empty($_GET['id'])){
    echo "잘못된 접근입니다.";
    exit;
}

$customer_id = (int)$_GET['id'];

// Fetch customer details using prepared statements
$stmt = mysqli_prepare($link, "SELECT * FROM customers WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$customer_result = mysqli_stmt_get_result($stmt);
if (mysqli_num_rows($customer_result) != 1) {
    echo "고객 정보를 찾을 수 없습니다.";
    exit;
}
$customer = mysqli_fetch_assoc($customer_result);
mysqli_stmt_close($stmt);

// Fetch contracts using prepared statements
$stmt = mysqli_prepare($link, "SELECT * FROM contracts WHERE customer_id = ? ORDER BY loan_date DESC");
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$contracts_result = mysqli_stmt_get_result($stmt);
$contracts = mysqli_fetch_all($contracts_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// ===================================================================
// ======================= 성능 최적화 로직 시작 =======================
// ===================================================================
// N+1 쿼리 문제를 해결하기 위해 계약 관련 데이터를 한 번에 가져옵니다.

$contract_ids = [];
if (!empty($contracts)) {
    $contract_ids = array_column($contracts, 'id');
}

$last_trans_dates = [];
$contract_memos_by_contract = [];

if (!empty($contract_ids)) {
    $contract_ids_str = implode(',', array_map('intval', $contract_ids));

    // 2. 모든 계약의 마지막 거래일 조회
    $sql_last_trans = "SELECT contract_id, MAX(collection_date) as last_date 
                       FROM collections 
                       WHERE contract_id IN ($contract_ids_str) 
                       GROUP BY contract_id";
    $result_last_trans = mysqli_query($link, $sql_last_trans);
    while ($row = mysqli_fetch_assoc($result_last_trans)) {
        $last_trans_dates[$row['contract_id']] = $row['last_date'];
    }

    // 3. 모든 계약의 메모 조회
    $sql_memos = "SELECT m.*, e.name as employee_name 
                  FROM contract_memos m 
                  LEFT JOIN employees e ON m.created_by = e.username 
                  WHERE m.contract_id IN ($contract_ids_str) 
                  ORDER BY m.contract_id, m.created_at DESC";
    $result_memos = mysqli_query($link, $sql_memos);
    while ($memo = mysqli_fetch_assoc($result_memos)) {
        $contract_memos_by_contract[$memo['contract_id']][] = $memo;
    }

    // 4. 모든 계약의 비용 조회 [NEW]
    $contract_expenses_by_contract = [];
    $sql_expenses = "SELECT * FROM contract_expenses WHERE contract_id IN ($contract_ids_str) ORDER BY expense_date DESC, id DESC";
    $result_expenses = mysqli_query($link, $sql_expenses);
    while ($exp = mysqli_fetch_assoc($result_expenses)) {
        $contract_expenses_by_contract[$exp['contract_id']][] = $exp;
    }
}
// ======================= 성능 최적화 로직 끝 =========================

// Fetch frequent memos
$frequent_memos_query = mysqli_query($link, "SELECT * FROM frequent_memos ORDER BY id ASC");
$frequent_memos = mysqli_fetch_all($frequent_memos_query, MYSQLI_ASSOC);

function get_status_display_detail($status) {
    $status_map = [
        'active' => '<span class="status-badge status-active">정상</span>',
        'paid' => '<span class="status-badge status-paid">완납</span>',
        'defaulted' => '<span class="status-badge status-defaulted">부실</span>',
        'overdue' => '<span class="status-badge status-overdue">연체</span>',
    ];
    return $status_map[$status] ?? htmlspecialchars($status ?? '');
}

function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf':
            return '📄';
        case 'doc':
        case 'docx':
        case 'hwp':
            return '📝';
        case 'xls':
        case 'xlsx':
            return '📊';
        case 'ppt':
        case 'pptx':
            return '🖥️';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'bmp':
        case 'webp':
        case 'tif':
        case 'tiff':
            return '🖼️';
        default:
            return '📁';
    }
}

$memo_colors = ['black' => '검정', 'red' => '빨강', 'blue' => '파랑', 'green' => '녹색', 'yellow' => '노랑', 'orange' => '오렌지', 'purple' => '보라'];


// Fetch previous and next customer IDs
// Fetch previous and next customer IDs and Names
$prev_id_query = mysqli_query($link, "SELECT id, name FROM customers WHERE id < $customer_id ORDER BY id DESC LIMIT 1");
$prev_customer = mysqli_fetch_assoc($prev_id_query);
$prev_id = $prev_customer['id'] ?? null;
$prev_name = $prev_customer['name'] ?? '';

$next_id_query = mysqli_query($link, "SELECT id, name FROM customers WHERE id > $customer_id ORDER BY id ASC LIMIT 1");
$next_customer = mysqli_fetch_assoc($next_id_query);
$next_id = $next_customer['id'] ?? null;
$next_name = $next_customer['name'] ?? '';

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2 style="margin-bottom: 0;">고객 상세 정보: <?php echo htmlspecialchars($customer['name']); ?></h2>
    <div>
        <?php if ($prev_id): ?>
            <a href="customer_detail.php?id=<?php echo $prev_id; ?>" class="btn btn-secondary">
                &lt; 이전고객 (<?php echo $prev_id; ?>: <?php echo htmlspecialchars($prev_name); ?>)
            </a>
        <?php else: ?>
            <button class="btn btn-secondary" disabled>이전고객 없음</button>
        <?php endif; ?>
        <?php if ($next_id): ?>
            <a href="customer_detail.php?id=<?php echo $next_id; ?>" class="btn btn-secondary">
                다음고객 (<?php echo $next_id; ?>: <?php echo htmlspecialchars($next_name); ?>) &gt;
            </a>
        <?php else: ?>
            <button class="btn btn-secondary" disabled>다음고객 없음</button>
        <?php endif; ?>
    </div>
</div>

<?php if(isset($_SESSION['message'])): ?>
<div class="msg"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
<?php endif; ?>

<?php if(isset($_SESSION['error_message'])): ?>
<div class="msg error-msg"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<!-- Customer Info Section -->
<div class="info-section-container">
    <h4>기본 정보</h4>
    <div class="info-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 25px; align-items: start;">
        <!-- Column 1 -->
        <div style="display: flex; flex-direction: column; gap: 10px;">
             <div class="info-item"><strong>고객명:</strong><span><strong><?php echo htmlspecialchars($customer['name']); ?></strong></span></div>
             <div class="info-item"><strong>주민번호:</strong><span><?php echo htmlspecialchars($customer['resident_id_partial']); ?></span></div>
             <div class="info-item"><strong>연락처:</strong><span><strong><?php echo htmlspecialchars($customer['phone']); ?></strong></span></div>
             <div class="info-item full-width"><strong>등본상 주소:</strong><span><?php echo htmlspecialchars($customer['address_registered']); ?></span></div>
             <div class="info-item full-width"><strong>실거주 주소:</strong><span><?php echo htmlspecialchars($customer['address_actual']); ?></span></div>
        </div>
        <!-- Column 2 -->
        <div style="display: flex; flex-direction: column; gap: 10px;">
             <div class="info-item"><strong>담당자:</strong><span><?php echo htmlspecialchars($customer['manager']); ?></span></div>
             <div class="info-item"><strong>신청거래처:</strong><span><?php echo htmlspecialchars($customer['application_source']); ?></span></div>
             <div class="info-item"><strong>대출신청금액:</strong><span><?php echo number_format($customer['requested_loan_amount']); ?> 원</span></div>
             <div class="info-item"><strong>대출신청일:</strong><span><?php echo htmlspecialchars($customer['loan_application_date']); ?></span></div>
             <div class="info-item full-width"><strong>입금계좌:</strong><span><?php echo htmlspecialchars($customer['bank_name'] . ' ' . $customer['account_number']); ?></span></div>
        </div>
        <!-- Column 3: Customer Memo -->
        <div style="display: flex; flex-direction: column; height: 100%;">
            <strong>고객메모:</strong>
            <div class="memo-display" style="flex-grow: 1;"><?php echo nl2br(htmlspecialchars($customer['memo'] ?? '')); ?></div>
        </div>
    </div>
</div>

<!-- =================================================================== -->
<!-- ======================= 첨부 파일 관리 섹션 시작 ======================= -->
<!-- =================================================================== -->
<div class="info-section-container" style="margin-top: 30px;">
    <h4>첨부 파일 관리</h4>

    <!-- 파일 업로드 폼 -->
    <div class="form-container" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">
        <h5>신규 파일 업로드</h5>
        <form id="file-upload-form" action="../process/file_process.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_file">
            <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
            <div class="form-col" style="margin-bottom: 10px;">
                <label for="customer_file">파일 선택</label>
                <input type="file" name="customer_file[]" id="customer_file" required multiple>
            </div>
            <div class="form-col" style="margin-bottom: 10px;">
                <label for="file_memo">메모 (선택)</label>
                <input type="text" name="memo" id="file_memo" placeholder="파일에 대한 설명을 입력하세요.">
            </div>
            <button type="submit" class="btn btn-primary">업로드</button>
        </form>
        <!-- 프로그레스 바 컨테이너 -->
        <div id="upload-progress-container" style="margin-top: 15px;">
            <!-- 프로그레스 바가 여기에 동적으로 추가됩니다. -->
        </div>
    </div>

    <!-- 업로드된 파일 목록 -->
    <div class="table-container">
        <table id="file-list-table">
            <thead>
                <tr>
                    <th style="width: 30%;">파일명</th>
                    <th>메모</th>
                    <th>업로더</th>
                    <th>업로드 일시</th>
                    <th>크기</th>
                    <th>작업</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $customer_files = getCustomerFiles($link, $customer_id);
                ?>
                <?php if (empty($customer_files)): ?>
                    <tr><td colspan="6" style="text-align: center;">업로드된 파일이 없습니다.</td></tr>
                <?php else: foreach ($customer_files as $file): ?>
                    <tr id="file-row-<?php echo $file['id']; ?>">
                        <td>
                            <span style="font-size: 1.2em; margin-right: 8px;"><?php echo getFileIcon($file['original_filename']); ?></span>
                            <?php echo htmlspecialchars($file['original_filename']); ?>
                        </td>
                        <td class="editable-memo" data-file-id="<?php echo $file['id']; ?>">
                            <span><?php echo htmlspecialchars($file['memo'] ?? ''); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($file['uploader_name']); ?></td>
                        <td><?php echo $file['uploaded_at']; ?></td>
                        <td><?php echo round($file['file_size'] / 1024, 1); ?> KB</td>
                        <td class="action-buttons">
                            <button class="btn btn-xs view-file-btn" 
                                    data-file-type="<?php echo htmlspecialchars($file['file_type']); ?>"
                                    data-file-name="<?php echo htmlspecialchars($file['original_filename']); ?>"
                                    data-file-id="<?php echo $file['id']; ?>">미리보기</button>
                            <a href="../process/file_process.php?action=download_file&file_id=<?php echo $file['id']; ?>" class="btn btn-xs">다운로드</a>
                            <button class="btn btn-xs del_btn delete-file-btn" data-file-id="<?php echo $file['id']; ?>">삭제</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- =================================================================== -->
<!-- ======================== 첨부 파일 관리 섹션 끝 ======================= -->
<!-- =================================================================== -->


<div style="margin: 25px 0;">
    <a href="contract_manage.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-success">신규 계약 추가</a>
</div>

<h3 class="section-title">계약 정보</h3>

<?php if (!empty($contracts)): foreach ($contracts as $contract): 
    $contract_id = $contract['id'];
    $today = new DateTime();
    $outstanding_principal = (float)$contract['current_outstanding_principal'];

    // Calculate interest accrued today
    $interest_data_today = calculateAccruedInterest($link, $contract, $today->format('Y-m-d'));
    $interest_accrued_today = $interest_data_today['total'];
    
    // Payoff amount
    $payoff_amount = $outstanding_principal + $interest_accrued_today + (float)$contract['shortfall_amount'];

    // 최적화: 미리 조회한 마지막 거래일 사용
    $last_trans_date_str = $last_trans_dates[$contract_id] ?? $contract['loan_date'];

    // 실시간 연체일수 계산 (DB status에 의존하지 않음)
    $overdue_days = 0;
    if (!empty($contract['next_due_date'])) {
        $next_due_date_obj = new DateTime($contract['next_due_date']);
        $today_start_of_day = (clone $today)->setTime(0, 0, 0); // 시간 부분을 제거하여 날짜만 비교
        if ($today_start_of_day > $next_due_date_obj) {
            $overdue_days = $today_start_of_day->diff($next_due_date_obj)->days;
        }
    }
?>
<div class="contract-card">
    <div class="contract-card-header">
        <div style="display: flex; align-items: center; gap: 10px;">
            <h3>계약번호: <?php echo $contract_id; ?> (<?php echo get_status_display_detail($contract['status']); ?>)</h3>
            <div class="classification-badges">
                <?php
                $assigned_codes = get_contract_classifications($link, $contract_id);
                foreach ($assigned_codes as $code) {
                    echo '<span class="badge bg-info text-dark" style="margin-right: 5px;">' . htmlspecialchars($code['code']) . ' - ' . htmlspecialchars($code['name']) . ' <i class="fas fa-times remove-classification-btn" data-contract-id="' . $contract_id . '" data-code-id="' . $code['id'] . '" style="cursor: pointer; margin-left: 3px;"></i></span>';
                }
                ?>
                <button type="button" class="btn btn-xs btn-outline-secondary add-classification-btn" data-contract-id="<?php echo $contract_id; ?>"><i class="fas fa-plus"></i> 구분코드</button>
            </div>
        </div>
        <a href="sms.php?contract_id=<?php echo $contract['id']; ?>" class="btn btn-primary">SMS 발송</a>
    </div>
    
    <div class="contract-details-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; align-items: start;">
        <!-- Column 1: 기본 정보 -->
        <div>
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
                        <span>[<?php echo htmlspecialchars($contract['rate_change_date'] ?? ''); ?>] 부터</span>
                        <span>(정상: <?php echo htmlspecialchars($contract['new_interest_rate'] ?? ''); ?>% / 연체: <?php echo htmlspecialchars($contract['new_overdue_rate'] ?? ''); ?>%)</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Column 2: 실시간 채권 상태 -->
        <div>
            <h4>실시간 채권 상태 (<?php echo $today->format('Y-m-d'); ?> 기준)</h4>
            <div class="info-grid-condensed">
                <div class="info-item"><strong>대출잔액:</strong><span class="highlight-blue"><?php echo number_format($outstanding_principal); ?> 원</span></div>
                <div class="info-item"><strong>오늘까지 발생이자:</strong><span><?php echo number_format($interest_accrued_today); ?> 원</span></div>
                <div class="info-item"><strong>미수/부족금:</strong><span><?php echo number_format($contract['shortfall_amount']); ?> 원</span></div>
                <div class="info-item"><strong>오늘 완납시 금액:</strong><span class="highlight-green"><?php echo number_format($payoff_amount); ?> 원</span></div>
                <div class="info-item"><strong>마지막 거래일:</strong><span><?php echo $last_trans_date_str; ?></span></div>
                <div class="info-item"><strong>다음 약정일:</strong><span><?php echo htmlspecialchars($contract['next_due_date'] ?? ''); ?></span></div>
                <div class="info-item"><strong>연체일수:</strong><span class="highlight-red"><?php echo $overdue_days; ?> 일</span></div>
            </div>
        </div>

        <!-- Column 3: 계약 메모 -->
        <div>
            <h4>계약 메모</h4>
            <div class="memo-list" id="memo-list-<?php echo $contract_id; ?>">
                <?php
                    // 최적화: 미리 조회한 메모 사용
                    $memos = $contract_memos_by_contract[$contract_id] ?? [];
                    if (empty($memos)):
                ?>
                <p class="no-memos">작성된 메모가 없습니다.</p>
                <?php else: foreach ($memos as $memo): ?>
                <div class="memo-item" style="border-left-color: <?php echo htmlspecialchars($memo['color']); ?>;">
                    <div class="memo-actions">
                        <button class="btn btn-sm edit-memo-btn" data-memo-id="<?php echo $memo['id']; ?>" data-memo-text="<?php echo htmlspecialchars($memo['memo_text']); ?>" data-memo-color="<?php echo htmlspecialchars($memo['color']); ?>">수정</button>
                        <form action="../process/memo_process.php" method="post" style="display: inline;" onsubmit="return confirm('정말 이 메모를 삭제하시겠습니까?');">
                            <input type="hidden" name="memo_id" value="<?php echo $memo['id']; ?>">
                            <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                            <button type="submit" name="delete_memo" class="btn btn-sm btn-danger">삭제</button>
                        </form>
                    </div>
                    <p class="memo-text"><?php echo nl2br(htmlspecialchars($memo['memo_text'])); ?></p>
                    <p class="memo-meta"><strong>작성자:</strong> <?php echo htmlspecialchars($memo['employee_name'] ?? $memo['created_by']); ?> | <strong>작성일:</strong> <?php echo $memo['created_at']; ?><?php if($memo['updated_at']): ?> | <strong>수정일:</strong> <?php echo $memo['updated_at']; ?><?php endif; ?></p>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="memo-form-container">
                <form action="../process/memo_process.php" method="post" class="memo-form" id="memo-form-<?php echo $contract_id; ?>">
                    <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                    <input type="hidden" name="memo_id" value="">
                    <h5><span id="form-title-<?php echo $contract_id; ?>">새 메모 작성</span></h5>
                    <div class="form-col">
                        <textarea name="memo_text" rows="4" placeholder="메모 내용을 입력하세요..." required></textarea>
                    </div>
                    <div class="form-grid memo-form-options">
                        <div class="form-col">
                            <label>자주 쓰는 메모</label>
                            <select name="frequent_memo" class="frequent-memo-select">
                                <option value="">선택</option>
                                <?php foreach($frequent_memos as $fm): ?>
                                <option value="<?php echo htmlspecialchars($fm['memo_text']); ?>"><?php echo htmlspecialchars($fm['memo_text']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-col">
                            <label>색상</label>
                            <select name="color">
                                <?php foreach($memo_colors as $color_val => $color_name): ?>
                                <option value="<?php echo $color_val; ?>"><?php echo $color_name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-buttons" style="text-align: right; margin-top: 15px;">
                        <button type="submit" name="save_memo" class="btn btn-primary">메모 저장</button>
                        <button type="button" class="btn btn-secondary cancel-edit-btn" style="display: none;">수정 취소</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Column 4: 비용 관리 [NEW] -->
        <div>
            <h4>비용 관리</h4>
            <div class="table-container" style="max-height: 300px; overflow-y: auto; margin-bottom: 15px;">
                <table class="table table-sm table-bordered" style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>날짜</th>
                            <th>내용</th>
                            <th>금액</th>
                            <th>상태</th>
                            <th>삭제</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $expenses = $contract_expenses_by_contract[$contract_id] ?? [];
                        if (empty($expenses)): ?>
                            <tr><td colspan="5" style="text-align: center;">등록된 비용이 없습니다.</td></tr>
                        <?php else: foreach ($expenses as $exp): ?>
                            <tr>
                                <td><?php echo $exp['expense_date']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($exp['description']); ?>
                                    <?php if($exp['remarks']) echo '<br><small style="color:#888;">' . htmlspecialchars($exp['remarks']) . '</small>'; ?>
                                </td>
                                <td style="text-align: right;"><?php echo number_format($exp['amount']); ?></td>
                                <td style="text-align: center;">
                                    <?php if(!empty($exp['is_processed'])): ?>
                                        <span class="badge bg-success">처리됨</span>
                                        <br><small><?php echo date('y-m-d', strtotime($exp['processed_date'])); ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">미처리</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if(empty($exp['is_processed'])): ?>
                                    <form action="../process/expense_process.php" method="post" onsubmit="return confirm('정말 이 비용 내역을 삭제하시겠습니까?');">
                                        <input type="hidden" name="action" value="delete_expense">
                                        <input type="hidden" name="expense_id" value="<?php echo $exp['id']; ?>">
                                        <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                                        <button type="button" class="btn btn-xs btn-danger delete-expense-btn" data-expense-id="<?php echo $exp['id']; ?>">삭제</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="form-container" style="padding: 10px; background-color: #f9f9f9; border: 1px solid #eee;">
                <h5>비용 추가</h5>
                <form action="../process/expense_process.php" method="post">
                    <input type="hidden" name="action" value="add_expense">
                    <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-col">
                            <label>발생일</label>
                            <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-col">
                            <label>금액</label>
                            <input type="text" name="amount" placeholder="금액" required oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');">
                        </div>
                    </div>
                    <div class="form-col" style="margin-top: 10px;">
                        <label>내용</label>
                        <input type="text" name="description" placeholder="예: 법무비용, 우편료" required>
                    </div>
                    <div class="form-col" style="margin-top: 10px;">
                        <label>비고</label>
                        <input type="text" name="remarks" placeholder="비고 (선택)">
                    </div>
                    <div style="text-align: right; margin-top: 10px;">
                        <button type="submit" class="btn btn-sm btn-primary">추가</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; else: ?>
    <div class="info-section-container"><p>이 고객의 계약 정보가 없습니다.</p></div>
<?php endif; ?>

<hr style="margin-top: 30px;">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <?php if ($prev_id): ?>
            <a href="customer_detail.php?id=<?php echo $prev_id; ?>" class="btn btn-secondary">
                &lt; 이전고객 (<?php echo $prev_id; ?>: <?php echo htmlspecialchars($prev_name); ?>)
            </a>
        <?php else: ?>
            <button class="btn btn-secondary" disabled>이전고객 없음</button>
        <?php endif; ?>
        <?php if ($next_id): ?>
            <a href="customer_detail.php?id=<?php echo $next_id; ?>" class="btn btn-secondary">
                다음고객 (<?php echo $next_id; ?>: <?php echo htmlspecialchars($next_name); ?>) &gt;
            </a>
        <?php else: ?>
            <button class="btn btn-secondary" disabled>다음고객 없음</button>
        <?php endif; ?>
    </div>
    <a href="customer_manage.php" class="btn btn-secondary">고객 목록으로 돌아가기</a>
</div>

<!-- 파일 미리보기 Modal -->
<!-- TIF 파일 미리보기를 위한 tiff.js 라이브러리 추가 -->
<script src="https://cdn.jsdelivr.net/npm/tiff.js/tiff.min.js"></script>

<div id="filePreviewModal" class="modal" style="display: none; z-index: 1050;">
    <div class="modal-content" style="width: 80%; max-width: 900px; height: 90vh; resize: both; overflow: auto; min-width: 300px; min-height: 200px; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-shrink: 0;">
            <h4 id="filePreviewTitle" style="margin: 0;"></h4>
            <div style="display: flex; gap: 10px; align-items: center;">
                <div id="zoom-controls" style="display: none;">
                    <button id="zoom-out-btn" class="btn btn-sm btn-secondary">-</button>
                    <span id="zoom-level">100%</span>
                    <button id="zoom-in-btn" class="btn btn-sm btn-secondary">+</button>
                    <button id="zoom-reset-btn" class="btn btn-sm btn-secondary">Reset</button>
                </div>
                <span class="close-button" id="filePreviewCloseBtn" style="cursor: pointer; font-size: 28px;">&times;</span>
            </div>
        </div>
        <div id="filePreviewBody" style="flex-grow: 1; overflow: auto; text-align: center; position: relative; background-color: #f0f0f0; display: flex; justify-content: center; align-items: center;">
            <!-- 미리보기 콘텐츠가 여기에 삽입됩니다. -->
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    function autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }

    document.querySelectorAll('.edit-memo-btn').forEach(button => {
        button.addEventListener('click', function() {
            const contractId = this.closest('.contract-card').querySelector('input[name="contract_id"]').value;
            const form = document.getElementById('memo-form-' + contractId);
            const memoId = this.dataset.memoId;
            const memoText = this.dataset.memoText;
            const memoColor = this.dataset.memoColor;
            form.querySelector('input[name="memo_id"]').value = memoId;
            const textarea = form.querySelector('textarea[name="memo_text"]');
            textarea.value = memoText;
            autoResizeTextarea(textarea); // 높이 조절
            form.querySelector('select[name="color"]').value = memoColor;
            document.getElementById('form-title-' + contractId).textContent = '메모 수정';
            form.querySelector('.cancel-edit-btn').style.display = 'inline-block';
            form.querySelector('textarea[name="memo_text"]').focus();
        });
    });

    document.querySelectorAll('.cancel-edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const contractId = this.closest('.contract-card').querySelector('input[name="contract_id"]').value;
            const form = document.getElementById('memo-form-' + contractId);
            form.reset(); // Reset form fields
            form.querySelector('input[name="memo_id"]').value = '';
            document.getElementById('form-title-' + contractId).textContent = '새 메모 작성';
            const textarea = form.querySelector('textarea[name="memo_text"]');
            autoResizeTextarea(textarea); // 높이 조절
            this.style.display = 'none';
        });
    });

    document.querySelectorAll('.frequent-memo-select').forEach(select => {
        select.addEventListener('change', function() {
            if (this.value) {
                const textarea = this.closest('.memo-form').querySelector('textarea[name="memo_text"]');
                textarea.value += (textarea.value ? '\n' : '') + this.value;
                autoResizeTextarea(textarea); // 높이 조절
                this.value = ''; // Reset select
            }
        });
    });

    // 계약 메모 textarea 자동 높이 조절
    document.querySelectorAll('.memo-form textarea[name="memo_text"]').forEach(textarea => {
        textarea.addEventListener('input', function() {
            autoResizeTextarea(this);
        });
    });

    // --- 파일 관리 스크립트 ---

    // 파일 업로드 처리
    const fileUploadForm = document.getElementById('file-upload-form');
    if(fileUploadForm) {
        fileUploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const files = document.getElementById('customer_file').files;
            const memo = document.getElementById('file_memo').value;
            const customerId = this.querySelector('input[name="customer_id"]').value;
            const progressContainer = document.getElementById('upload-progress-container');

            if (files.length === 0) {
                alert('업로드할 파일을 선택해주세요.');
                return;
            }

            progressContainer.innerHTML = ''; // 이전 프로그레스 바 초기화
            const uploadPromises = [];

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const fileId = `file-progress-${i}`;

                // 프로그레스 바 UI 생성
                const progressWrapper = document.createElement('div');
                progressWrapper.className = 'progress-wrapper';
                progressWrapper.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; margin-bottom: 5px;">
                        <span>${file.name}</span>
                        <span id="${fileId}-status">0%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div id="${fileId}" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                `;
                progressContainer.appendChild(progressWrapper);

                const formData = new FormData();
                formData.append('action', 'upload_file');
                formData.append('customer_id', customerId);
                formData.append('memo', memo);
                formData.append('customer_file[]', file);

                // XMLHttpRequest를 사용한 업로드 프로미스 생성
                const uploadPromise = new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '../process/file_process.php', true);

                    xhr.upload.onprogress = function(event) {
                        if (event.lengthComputable) {
                            const percentComplete = Math.round((event.loaded / event.total) * 100);
                            const progressBar = document.getElementById(fileId);
                            const statusSpan = document.getElementById(`${fileId}-status`);
                            progressBar.style.width = percentComplete + '%';
                            progressBar.setAttribute('aria-valuenow', percentComplete);
                            statusSpan.textContent = percentComplete + '%';
                        }
                    };

                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            document.getElementById(`${fileId}-status`).textContent = '완료';
                            resolve(JSON.parse(xhr.responseText));
                        } else {
                            document.getElementById(`${fileId}-status`).textContent = '실패';
                            reject(new Error('Upload failed with status: ' + xhr.status));
                        }
                    };
                    xhr.onerror = function() { reject(new Error('Network error.')); };
                    xhr.send(formData);
                });
                uploadPromises.push(uploadPromise);
            }

            // 모든 업로드가 완료된 후 처리
            Promise.all(uploadPromises).then(results => {
                const allSuccess = results.every(res => res.success);
                if (allSuccess) {
                    alert('모든 파일이 성공적으로 업로드되었습니다.');
                } else {
                    alert('일부 파일 업로드에 실패했습니다. 목록을 확인해주세요.');
                }
                location.reload();
            }).catch(error => {
                console.error('An error occurred during upload:', error);
                alert('파일 업로드 중 심각한 오류가 발생했습니다.');
                location.reload();
            });
        });
    }

    // --- 비용 삭제 AJAX 처리 ---
    document.body.addEventListener('click', function(e) {
        if (e.target.classList.contains('delete-expense-btn')) {
            const btn = e.target;
            const form = btn.closest('form');
            const expenseId = form.querySelector('input[name="expense_id"]').value;
            const customerId = form.querySelector('input[name="customer_id"]').value;

            if (!confirm('정말 이 비용 내역을 삭제하시겠습니까?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_expense');
            formData.append('expense_id', expenseId);
            formData.append('customer_id', customerId);

            fetch('../process/expense_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            });
        }
    });

    // 파일 삭제, 미리보기 이벤트 위임 (Delegation)
    const fileListTable = document.getElementById('file-list-table');
    if(fileListTable) {
        const originalMemoMap = new Map(); // 수정 취소를 위한 원본 메모 저장

        fileListTable.addEventListener('click', function(e) {
            // 삭제 버튼 클릭 시
            if (e.target.classList.contains('delete-file-btn')) {
                const fileId = e.target.dataset.fileId;
                if (!confirm('정말 이 파일을 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) {
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'delete_file');
                formData.append('file_id', fileId);

                fetch('../process/file_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        document.getElementById('file-row-' + fileId).remove();
                    }
                });
            }

            // --- 메모 수정 관련 로직 ---

            // 메모 셀 클릭 시 편집 모드로 전환
            if (e.target.tagName === 'SPAN' && e.target.parentElement.classList.contains('editable-memo')) {
                const td = e.target.parentElement;
                if (td.classList.contains('editing')) return; // 이미 편집 중이면 무시

                td.classList.add('editing');
                const fileId = td.dataset.fileId;
                const currentMemo = e.target.textContent;
                originalMemoMap.set(fileId, currentMemo); // 원본 메모 저장

                td.innerHTML = `
                    <input type="text" class="form-control form-control-sm" value="${currentMemo}">
                    <div style="margin-top: 5px; text-align: right;">
                        <button class="btn btn-xs btn-primary save-memo-btn">저장</button>
                        <button class="btn btn-xs btn-secondary cancel-memo-btn">취소</button>
                    </div>
                `;
                td.querySelector('input').focus();
            }

            // 메모 저장 버튼 클릭
            if (e.target.classList.contains('save-memo-btn')) {
                const td = e.target.closest('td.editable-memo');
                const input = td.querySelector('input');
                const newMemo = input.value;
                const fileId = td.dataset.fileId;

                const formData = new FormData();
                formData.append('action', 'update_file_memo');
                formData.append('file_id', fileId);
                formData.append('memo', newMemo);

                fetch('../process/file_process.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            td.innerHTML = `<span>${newMemo}</span>`;
                            td.classList.remove('editing');
                        } else {
                            alert(data.message);
                        }
                    });
            }

            // 메모 수정 취소 버튼 클릭
            if (e.target.classList.contains('cancel-memo-btn')) {
                const td = e.target.closest('td.editable-memo');
                const fileId = td.dataset.fileId;
                td.innerHTML = `<span>${originalMemoMap.get(fileId) || ''}</span>`;
                td.classList.remove('editing');
            }

            // 미리보기 버튼 클릭 시
            if (e.target.classList.contains('view-file-btn')) {
                const modal = document.getElementById('filePreviewModal');
                const title = document.getElementById('filePreviewTitle');
                const body = document.getElementById('filePreviewBody');
                const fileType = e.target.dataset.fileType;
                const fileName = e.target.dataset.fileName;
                const fileId = e.target.dataset.fileId;

                title.textContent = fileName;
                body.innerHTML = ''; // 이전 내용 초기화

                // 이미지, PDF 미리보기는 파일 내용을 직접 로드하여 처리
                const isPreviewableImage = fileType.startsWith('image/');
                const isPdf = fileType === 'application/pdf';

                // 줌 관련 변수 초기화
                let currentScale = 1.0;
                const zoomControls = document.getElementById('zoom-controls');
                const zoomLevelDisplay = document.getElementById('zoom-level');
                const filePreviewBody = document.getElementById('filePreviewBody');
                
                // 줌 컨트롤 표시 여부 설정
                if (isPreviewableImage || isPdf || fileType === 'image/tiff' || fileType === 'image/tif') {
                     // PDF는 iframe 내부 줌이 브라우저에 따라 다르므로 일단 컨트롤은 표시하되 기능은 제한적일 수 있음. 
                     // 하지만 이미지/TIF는 직접 구현.
                     if(isPdf) {
                         zoomControls.style.display = 'none'; // PDF는 자체 뷰어 사용
                     } else {
                         zoomControls.style.display = 'flex';
                     }
                } else {
                    zoomControls.style.display = 'none';
                }

                const updateZoom = (scale) => {
                    currentScale = scale;
                    zoomLevelDisplay.textContent = Math.round(currentScale * 100) + '%';
                    
                    const content = filePreviewBody.querySelector('img, canvas');
                    if (content) {
                        content.style.transform = `scale(${currentScale})`;
                        content.style.transformOrigin = 'top left'; // 스크롤 가능하도록 top left 기준
                        
                        // 컨텐츠가 작아졌을 때 중앙 정렬 유지를 위해 margin 조정 (선택적)
                        // 하지만 transform은 layout 공간을 차지하지 않으므로, 
                        // 실제로는 컨테이너의 overflow와 scroll 동작을 위해 wrapper가 필요할 수 있음.
                        // 간단한 구현을 위해 transform만 적용하고 overflow:auto인 부모에서 스크롤.
                        
                        // transform scale을 쓰면 스크롤 영역이 제대로 안 잡힐 수 있음.
                        // width/height를 직접 조절하거나, 내부 wrapper를 두고 그 wrapper의 크기를 조절하는 방식이 나음.
                        // 여기서는 간단히 width 스타일을 조절하는 방식으로 변경 (이미지/캔버스)
                        
                        if (content.tagName === 'IMG') {
                             // 이미지의 경우 naturalWidth 기준
                             // content.style.width = (content.naturalWidth * currentScale) + 'px';
                             // content.style.maxWidth = 'none'; // 부모 크기 제한 해제
                             
                             // transform 방식이 화질 저하가 적으므로 transform 사용하되, 
                             // 부모 div에 충분한 스크롤 영역을 확보해주기 위해 빈 div 등을 활용하거나
                             // 단순히 transform만 적용.
                             
                             content.style.transform = `scale(${currentScale})`;
                             // transform 후 스크롤 영역 확보를 위해 margin 등을 조절해야 하는데 복잡함.
                             // 가장 쉬운 방법: zoom-wrapper div를 만들고 그 안에 넣기.
                        }
                    }
                    
                    // Zoom Wrapper 방식 적용
                    const wrapper = document.getElementById('zoom-wrapper');
                    if(wrapper) {
                        wrapper.style.transform = `scale(${currentScale})`;
                        wrapper.style.transformOrigin = 'center center'; // 중앙 확대
                        // 스크롤 문제 해결을 위해 transformOrigin을 top left로 하고 
                        // wrapper의 부모가 overflow:auto여야 함.
                        // 하지만 중앙 정렬과 스크롤을 동시에 만족하기 까다로움.
                        
                        // 개선된 방식: width/height %로 제어 (반응형 유지)
                        // 또는 transform 사용 시 origin을 0 0 으로 하고, 부모 div의 크기를 JS로 늘려줌.
                    }
                };

                // 줌 이벤트 리스너 (중복 방지를 위해 기존 리스너 제거 필요하지만, 
                // 모달이 닫힐 때 초기화하거나, onclick 속성으로 덮어쓰기)
                document.getElementById('zoom-in-btn').onclick = () => updateZoom(currentScale + 0.1);
                document.getElementById('zoom-out-btn').onclick = () => updateZoom(Math.max(0.1, currentScale - 0.1));
                document.getElementById('zoom-reset-btn').onclick = () => updateZoom(1.0);


                if (!isPreviewableImage && !isPdf) {
                    body.innerHTML = `<p>이 파일 형식은 미리보기를 지원하지 않습니다.</p><a href="../process/file_process.php?action=download_file&file_id=${fileId}" class="btn btn-primary">다운로드</a>`;
                    modal.style.display = 'block';
                    return;
                }

                // 로딩 스피너 표시
                body.innerHTML = '<p>파일을 불러오는 중입니다...</p>';
                modal.style.display = 'block';

                // 줌 적용을 위한 래퍼 생성 함수
                const createZoomWrapper = (contentElement) => {
                    const wrapper = document.createElement('div');
                    wrapper.id = 'zoom-wrapper';
                    wrapper.style.display = 'inline-block';
                    wrapper.style.transition = 'transform 0.2s ease';
                    wrapper.appendChild(contentElement);
                    return wrapper;
                };

                if (fileType === 'image/tiff' || fileType === 'image/tif') {
                    if (typeof Tiff === 'undefined') {
                        body.innerHTML = `<p>미리보기 라이브러리를 로드하지 못했습니다. 페이지를 새로고침 후 다시 시도해주세요.</p><a href="../process/file_process.php?action=download_file&file_id=${fileId}" class="btn btn-primary">다운로드</a>`;
                        // modal.style.display is already set
                        return;
                    }

                    let tiffInstance = null;
                    let currentPage = 0;

                    const renderTiffPage = (pageNumber) => {

                        if (!tiffInstance) return;
                        const numPages = tiffInstance.countDirectory();
                        if (pageNumber < 0 || pageNumber >= numPages) return;

                        currentPage = pageNumber;
                        tiffInstance.setDirectory(pageNumber);
                        const canvas = tiffInstance.toCanvas();
                        // canvas.style.maxWidth = '100%'; // 줌 기능을 위해 제거
                        // canvas.style.maxHeight = '100%'; // 줌 기능을 위해 제거
                        canvas.style.display = 'block'; // canvas는 inline-block이라 여백 생길 수 있음

                        const canvasContainer = body.querySelector('#tiff-canvas-container');
                        canvasContainer.innerHTML = ''; // 이전 캔버스 제거
                        
                        // Zoom Wrapper 적용
                        const wrapper = createZoomWrapper(canvas);
                        canvasContainer.appendChild(wrapper);
                        
                        updateZoom(1.0); // 줌 초기화

                        if (numPages > 1) {
                            body.querySelector('#tiff-page-info').textContent = `${currentPage + 1} / ${numPages} 페이지`;
                            body.querySelector('#tiff-prev-btn').disabled = (currentPage === 0);
                            body.querySelector('#tiff-next-btn').disabled = (currentPage >= numPages - 1);
                        }
                    };

                    // 파일 내용을 Base64로 가져와서 처리
                    fetch(`../process/file_process.php?action=get_file_content&file_id=${fileId}`)
                    .then(response => response.json())
                    .then(data => {
                        tiffInstance = new Tiff({ buffer: _base64ToArrayBuffer(data.content) });
                        const numPages = tiffInstance.countDirectory();

                        if (numPages > 1) {
                            // 다중 페이지 UI 생성
                            body.innerHTML = `
                                <div id="tiff-canvas-container" style="flex-grow: 1; overflow: auto; display: flex; justify-content: center; align-items: center; width: 100%; height: calc(100% - 40px);"></div>
                                <div id="tiff-nav" style="height: 40px; display: flex; justify-content: center; align-items: center; gap: 15px; padding-top: 10px; flex-shrink: 0;">
                                    <button id="tiff-prev-btn" class="btn btn-sm btn-secondary">&lt; 이전</button>
                                    <span id="tiff-page-info"></span>
                                    <button id="tiff-next-btn" class="btn btn-sm btn-secondary">다음 &gt;</button>
                                </div>
                            `;
                            document.getElementById('tiff-prev-btn').addEventListener('click', () => renderTiffPage(currentPage - 1));
                            document.getElementById('tiff-next-btn').addEventListener('click', () => renderTiffPage(currentPage + 1));
                        } else {
                            // 단일 페이지 UI
                            body.innerHTML = '<div id="tiff-canvas-container" style="width: 100%; height: 100%; overflow: auto; display: flex; justify-content: center; align-items: center;"></div>';
                        }
                        
                        renderTiffPage(0); // 첫 페이지 렌더링

                    }).catch(error => {
                        console.error('TIF 렌더링 오류:', error);
                        body.innerHTML = `<p>TIF 파일을 미리보는 중 오류가 발생했습니다.</p><a href="../process/file_process.php?action=download_file&file_id=${fileId}" class="btn btn-primary">다운로드</a>`;
                    });
                } else if (fileType.startsWith('image/')) {
                    // 일반 이미지 파일 (JPG, PNG 등)
                    fetch(`../process/file_process.php?action=get_file_content&file_id=${fileId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const imageUrl = `data:${fileType};base64,${data.content}`;
                            const img = document.createElement('img');
                            img.src = imageUrl;
                            // img.style.maxWidth = '100%'; // 줌 기능을 위해 제거
                            // img.style.maxHeight = '100%'; // 줌 기능을 위해 제거
                            
                            // Zoom Wrapper 적용
                            const wrapper = createZoomWrapper(img);
                            
                            // 이미지 로드 후 초기화 (크기 계산 등을 위해)
                            img.onload = () => {
                                updateZoom(1.0);
                            };

                            body.innerHTML = '';
                            // 중앙 정렬 및 스크롤을 위한 컨테이너
                            const container = document.createElement('div');
                            container.style.width = '100%';
                            container.style.height = '100%';
                            container.style.overflow = 'auto';
                            container.style.display = 'flex';
                            container.style.justifyContent = 'center';
                            container.style.alignItems = 'center';
                            container.appendChild(wrapper);
                            
                            body.appendChild(container);
                            
                        } else {
                            throw new Error(data.message);
                        }
                    }).catch(error => {
                        console.error('이미지 로딩 오류:', error);
                        body.innerHTML = `<p>이미지를 불러오는 중 오류가 발생했습니다.</p><a href="../process/file_process.php?action=download_file&file_id=${fileId}" class="btn btn-primary">다운로드</a>`;
                    });
                } else if (fileType === 'application/pdf') {
                    // PDF 파일은 다운로드 링크를 iframe으로 표시
                    const pdfUrl = `../process/file_process.php?action=download_file&file_id=${fileId}&inline=1`;
                    body.innerHTML = `<iframe src="${pdfUrl}" style="width: 100%; height: 100%; border: none;"></iframe>`;
                } else {
                    // 이 코드는 위쪽의 isPreviewableImage, isPdf 체크로 인해 실행되지 않지만 안전을 위해 남겨둡니다.
                    body.innerHTML = `<p>이 파일 형식은 미리보기를 지원하지 않습니다.</p><a href="../process/file_process.php?action=download_file&file_id=${fileId}" class="btn btn-primary">다운로드</a>`;
                }
            }
        });
    }

    // 미리보기 모달 닫기
    const filePreviewModal = document.getElementById('filePreviewModal');
    if(filePreviewModal) {
        document.getElementById('filePreviewCloseBtn').onclick = () => {
            filePreviewModal.style.display = 'none';
        };
        window.addEventListener('click', (event) => {
            if (event.target == filePreviewModal) {
                filePreviewModal.style.display = 'none';
            }
        });
    }

    // Base64 문자열을 ArrayBuffer로 변환하는 헬퍼 함수
    function _base64ToArrayBuffer(base64) {
        const binary_string = window.atob(base64);
        const len = binary_string.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binary_string.charCodeAt(i);
        }
        return bytes.buffer;
    }

    // --- Classification Code Management Scripts ---
    // Add Classification Code
    document.querySelectorAll('.add-classification-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const contractId = this.dataset.contractId;
            const codes = <?php echo json_encode(get_all_classification_codes($link)); ?>;
            
            if (codes.length === 0) {
                alert('등록된 구분코드가 없습니다. 설정 페이지에서 먼저 등록해주세요.');
                return;
            }

            // Simple prompt for now, can be improved to a modal
            let message = "추가할 구분코드를 선택하세요 (번호 입력):\n";
            codes.forEach((c, index) => {
                message += `${index + 1}. ${c.code} - ${c.name}\n`;
            });

            const selection = prompt(message);
            if (selection) {
                const selectedIndex = parseInt(selection) - 1;
                if (selectedIndex >= 0 && selectedIndex < codes.length) {
                    const selectedCode = codes[selectedIndex];
                    updateContractClassification(contractId, selectedCode.id, 'add');
                } else {
                    alert('잘못된 선택입니다.');
                }
            }
        });
    });

    // Remove Classification Code
    document.querySelectorAll('.remove-classification-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('이 구분코드를 해제하시겠습니까?')) return;
            const contractId = this.dataset.contractId;
            const codeId = this.dataset.codeId;
            updateContractClassification(contractId, codeId, 'remove');
        });
    });

    function updateContractClassification(contractId, codeId, operation) {
        const formData = new FormData();
        formData.append('action', 'update_contract_classification');
        formData.append('contract_id', contractId);
        formData.append('classification_code_id', codeId);
        formData.append('operation', operation);

        fetch('../process/contract_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('오류: ' + (data.message || '작업 실패'));
            }
        })
        .catch(error => console.error('Error:', error));
    }
});
</script>

<?php include 'footer.php'; ?>