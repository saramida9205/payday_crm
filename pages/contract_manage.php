<?php
include_once('../process/contract_process.php');
include_once('../process/customer_process.php');
include_once('header.php');

if (session_status() == PHP_SESSION_NONE) session_start();
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if (isset($_GET['limit'])) {
    if ($_GET['limit'] === 'all') {
        $_SESSION['contract_limit'] = 'all';
    } else {
        $_SESSION['contract_limit'] = (int)$_GET['limit'];
    }
    $limit = $_SESSION['contract_limit'];
} else {
    $limit = $_SESSION['contract_limit'] ?? 20;
}
$search_params = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? 'valid',
    'agreement_date' => $_GET['agreement_date'] ?? '',
    'next_due_date' => $_GET['next_due_date'] ?? '',
    'loan_date_start' => $_GET['loan_date_start'] ?? '',
    'loan_date_end' => $_GET['loan_date_end'] ?? '',
    'classification_codes' => $_GET['classification_codes'] ?? [],
    'classification_logic' => $_GET['classification_logic'] ?? 'OR',
    'page' => $page,
    'limit' => $limit,
    'sort' => $_GET['sort'] ?? 'loan_date_desc' // 기본 정렬: 대출일 내림차순
];

$limit_options = [5, 10, 15, 20, 50, 100, 200, 300, 'all'];
$contract_data = getContracts($link, $search_params);
$contracts = $contract_data['data'];
$total_records = $contract_data['total'];
$total_loan_amount = $contract_data['total_loan_amount'] ?? 0;
$total_outstanding_balance = $contract_data['total_outstanding_balance'] ?? 0;
$total_expected_interest = $contract_data['total_expected_interest'] ?? 0;
$total_overdue_count = $contract_data['total_overdue_count'] ?? 0;
$total_overdue_amount = $contract_data['total_overdue_amount'] ?? 0;
$total_pages = ($limit !== 'all' && $limit > 0) ? ceil($total_records / $limit) : 1;

// 오늘 날짜
$today = date('Y-m-d');

// 마지막 배치 실행 시간 조회를 위해 company_info 데이터 가져오기
$company_info = get_all_company_info($link);
$last_batch_run = $company_info['last_daily_batch_run'] ?? '실행 기록 없음';

$banks = ['경남은행', '광주은행', 'KB국민은행', 'iM뱅크', 'NH농협은행', 'IBK기업은행', '대구은행', '부산은행', '산림조합중앙회', '상호저축은행', '저축은행중앙회', '새마을금고', '신용협동조합', '신한은행', '수협은행', 'SC제일은행', '우리은행', '우체국', '전북은행', '제주은행', '카카오뱅크', '케이뱅크', '토스뱅크', '하나은행', '한국산업은행', '한국수출입은행', '한국씨티은행', '미래에셋증권', '삼성증권', '신한투자증권', '키움증권', '한국투자증권', 'NH투자증권'];
sort($banks);
$products = ['신용대출', '주택담보대출', '토지담보대출', '상가담보대출', '자동차담보대출', '기타'];
$due_days = [1, 5, 6, 10, 11, 15, 16, 20, 21, 25, 26];
$statuses = ['active' => '정상', 'overdue' => '연체', 'paid' => '완납', 'defaulted' => '부실', 'etc' => '기타'];

// Fetch all classification codes for the search filter
$all_codes = get_all_classification_codes($link);

// --- Initialize variables ---
$update = false;
$id = 0;
$customer_id = 0;
$customer_data = null;
$product_name = '';
$agreement_date = '';
$loan_amount = '';
$repayment_method = '';
$monthly_installment = '';
$loan_date = '';
$maturity_date = '';
$manager = '';
$collateral_address = '';
$bank_name = '';
$account_number = '';
$account_holder = '';
$interest_rate = '';
$overdue_interest_rate = '';
$prepayment_rate = '';
$next_due_date = '';
$status = '';
$rate_change_date = '';
$new_interest_rate = '';
$new_overdue_rate = '';
$deferred_agreement_amount = 0;

if (isset($_GET['edit'])) {
    $update = true;
    $id = (int)$_GET['edit'];
    $contract_to_edit = getContractById($link, $id);
    if ($contract_to_edit) {
        foreach ($contract_to_edit as $key => $value) {
            $$key = $value; // Dynamically create variables from array keys
        }
        $customer_data = getCustomerById($link, $customer_id);
    }
} elseif (isset($_GET['customer_id'])) {
    $customer_id = (int)$_GET['customer_id'];
    $customer_data = getCustomerById($link, $customer_id);
}
?>

<h2>계약 목록</h2>

<!-- Search Form -->
<div class="search-form-container">
    <form action="contract_manage.php" method="get">
        <div class="search-form-flex">
            <div class="form-col" style="flex: 2;"><label>통합 검색</label><input type="text" name="search" placeholder="계약번호, 고객번호, 고객명" value="<?php echo htmlspecialchars($search_params['search']); ?>"></div>
            <div class="form-col"><label>상태</label><select name="status">
                    <option value="valid" <?php if ($search_params['status'] == 'valid') echo 'selected'; ?>>유효(정상,연체)</option>
                    <option value="" <?php if ($search_params['status'] == '') echo 'selected'; ?>>전체</option><?php foreach ($statuses as $key => $value): ?><option value="<?php echo $key; ?>" <?php if ($search_params['status'] == $key) echo 'selected'; ?>><?php echo $value; ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-col"><label>약정일</label><select name="agreement_date">
                    <option value="">전체</option><?php foreach ($due_days as $day): ?><option value="<?php echo $day; ?>" <?php if ($search_params['agreement_date'] == $day) echo 'selected'; ?>><?php echo $day; ?>일</option><?php endforeach; ?>
                </select></div>
            <div class="form-col"><label>상환일</label><input type="date" name="next_due_date" value="<?php echo htmlspecialchars($search_params['next_due_date'] ?? ''); ?>"></div>
            <div class="form-col"><label>대출일 (시작)</label><input type="date" name="loan_date_start" value="<?php echo htmlspecialchars($search_params['loan_date_start']); ?>"></div>
            <div class="form-col"><label>대출일 (종료)</label><input type="date" name="loan_date_end" value="<?php echo htmlspecialchars($search_params['loan_date_end']); ?>"></div>
            <div class="form-col"><label>표시 수</label><select name="limit" onchange="this.form.submit()"><?php foreach ($limit_options as $opt): ?><option value="<?php echo $opt; ?>" <?php if ($limit == $opt) echo 'selected'; ?>><?php echo $opt; ?></option><?php endforeach; ?></select></div>
            <div class="form-col"><label>정렬</label><select name="sort" onchange="this.form.submit()">
                    <option value="id_desc" <?php if ($search_params['sort'] == 'id_desc') echo 'selected'; ?>>계약번호 내림차순</option>
                    <option value="id_asc" <?php if ($search_params['sort'] == 'id_asc') echo 'selected'; ?>>계약번호 오름차순</option>
                    <option value="loan_date_desc" <?php if ($search_params['sort'] == 'loan_date_desc') echo 'selected'; ?>>대출일 내림차순</option>
                    <option value="loan_date_asc" <?php if ($search_params['sort'] == 'loan_date_asc') echo 'selected'; ?>>대출일 오름차순</option>
                </select>
            </div>
            <div class="form-col" style="flex: 100%; margin-top: 10px;">
                <div style="display: flex; align-items: flex-start; gap: 10px; border: 1px solid #ced4da; padding: 10px; border-radius: 4px; background-color: #fff;">
                    <div style="flex-shrink: 0; font-weight: bold; margin-right: 10px;">구분코드 검색</div>
                    <div style="flex-grow: 1; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 5px; max-height: 150px; overflow-y: auto;">
                        <?php
                        $selected_codes = $_GET['classification_codes'] ?? [];
                        foreach ($all_codes as $code) {
                            $checked = in_array($code['id'], $selected_codes) ? 'checked' : '';
                            echo '<div style="display: flex; align-items: center;">';
                            echo '<input type="checkbox" name="classification_codes[]" value="' . $code['id'] . '" id="code_' . $code['id'] . '" ' . $checked . ' style="margin-right: 5px;">';
                            echo '<label for="code_' . $code['id'] . '" style="margin-bottom: 0; font-size: 13px;">' . htmlspecialchars($code['code']) . '. ' . htmlspecialchars($code['name']) . '</label>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <div style="flex-shrink: 0; margin-left: 10px;">
                        <select name="classification_logic" style="width: 80px;">
                            <option value="OR" <?php if (($search_params['classification_logic'] ?? '') == 'OR') echo 'selected'; ?>>포함(OR)</option>
                            <option value="AND" <?php if (($search_params['classification_logic'] ?? '') == 'AND') echo 'selected'; ?>>모두(AND)</option>
                            <option value="EXCLUDE" <?php if (($search_params['classification_logic'] ?? '') == 'EXCLUDE') echo 'selected'; ?>>제외</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="search-form-buttons">
            <button type="submit" class="btn btn-primary">검색</button>
            <a href="contract_manage.php" class="btn btn-secondary">초기화</a>
        </div>
    </form>
</div>

<!-- Action Buttons -->
<div class="page-action-buttons">
    <button id="show_add_form_btn" class="btn btn-success">신규 계약 추가</button>
    <button id="download_selected_btn" class="btn btn-secondary">선택 항목 엑셀 다운로드</button>
    <button id="bulk_classification_btn" class="btn btn-info">구분코드 일괄 적용</button>
    <?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
        <div style="display: inline-block; text-align: center;">
            <button id="run_daily_batch_btn" class="btn btn-warning">일일 배치 실행</button>
            <div style="font-size: 11px; color: #666;">(최근: <?php echo htmlspecialchars($last_batch_run); ?>)</div>
        </div>
    <?php endif; ?>
</div>

<!-- Bulk Classification Modal -->
<div id="bulkClassificationModal" class="modal">
    <div class="modal-content" style="height: auto; width: 400px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3>구분코드 일괄 적용</h3>
            <span class="close-button">&times;</span>
        </div>
        <div class="form-col">
            <label>구분코드 선택</label>
            <select id="bulk_classification_select" class="form-control">
                <option value="">선택하세요</option>
                <?php
                $all_codes = get_all_classification_codes($link);
                foreach ($all_codes as $code) {
                    echo '<option value="' . $code['id'] . '">' . htmlspecialchars($code['code']) . ' - ' . htmlspecialchars($code['name']) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="form-col" style="margin-top: 15px;">
            <label>작업 선택</label>
            <div style="display: flex; gap: 10px;">
                <label><input type="radio" name="bulk_operation" value="add" checked> 추가</label>
                <label><input type="radio" name="bulk_operation" value="remove"> 해제</label>
            </div>
        </div>
        <div style="text-align: right; margin-top: 20px;">
            <button id="confirm_bulk_classification_btn" class="btn btn-primary">적용</button>
        </div>
    </div>
</div>

<!-- Summary Section -->
<div class="summary-container">
    <div class="summary-item">
        <div class="summary-label">조회 건수</div>
        <div class="summary-value"><?php echo number_format($total_records); ?> 건</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">대출금액 합계</div>
        <div class="summary-value"><?php echo number_format($total_loan_amount); ?> 원</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">현재잔액 합계</div>
        <div class="summary-value" style="color: #007bff;"><?php echo number_format($total_outstanding_balance); ?> 원</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">예상 이자 합계</div>
        <div class="summary-value" style="color: #28a745;"><?php echo number_format($total_expected_interest); ?> 원</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">연체 건수</div>
        <div class="summary-value" style="color: #dc3545;"><?php echo number_format($total_overdue_count); ?> 건</div>
    </div>
    <div class="summary-item">
        <div class="summary-label">연체금액 합계</div>
        <div class="summary-value" style="color: #dc3545;"><?php echo number_format($total_overdue_amount); ?> 원</div>
    </div>
</div>

<!-- Contract List Table -->
<div class="table-container">
    <table class="contract-list-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="select_all_contracts"></th>
                <th style="text-align: center;">고객번호</th>
                <th style="text-align: center;">계약번호<br>고객명</th>
                <th style="text-align: center;">상품명</th>
                <th style="text-align: center;">대출금액<br>
                    <font color="blue">현재잔액</font>
                </th>
                <th style="text-align: center;">대출일<br>-만기일</th>
                <th style="text-align: center;">약정일</th>
                <th style="text-align: center;">이자율<br>연체이자율</th>
                <th style="text-align: center;">최근입금일</th>
                <th style="text-align: center;">다음입금예정일</th>
                <th style="text-align: center;">상태</th>
                <th style="text-align: left; width: 280px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($contracts) && is_array($contracts)): foreach ($contracts as $contract): ?>
                    <?php
                    $row_style = '';
                    if (!empty($contract['next_due_date'])) {
                        if ($contract['next_due_date'] < $today) {
                            $row_style = 'style="background-color: #f8d7da;"'; // Past due: light red
                        } elseif ($contract['next_due_date'] == $today) {
                            $row_style = 'style="background-color: #f3fa97ff;"'; // Due today: light yellow
                        }
                    }
                    ?>
                    <tr data-contract-id="<?php echo htmlspecialchars($contract['id']); ?>" <?php echo $row_style; ?>>
                        <td><input type="checkbox" class="contract_checkbox" value="<?php echo htmlspecialchars($contract['id']); ?>"></td>
                        <td>
                            <div style="font-weight: bold; margin-bottom: 4px;"><?php echo htmlspecialchars($contract['customer_id']); ?></div>
                            <?php
                            $assigned_codes = get_contract_classifications($link, $contract['id']);
                            if (!empty($assigned_codes)) {
                                echo '<div style="display: flex; flex-wrap: wrap; gap: 2px;">';
                                foreach ($assigned_codes as $code) {
                                    echo '<span class="badge bg-info text-dark" style="font-size: 0.85em; font-color: #3c54a1cc; padding: 3px 6px; border-radius: 4px;" title="' . htmlspecialchars($code['name']) . '">' . htmlspecialchars($code['code']) . '</span>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </td>
                        <td><a href="customer_detail.php?id=<?php echo $contract['customer_id']; ?>"><?php echo htmlspecialchars($contract['id']); ?><br><?php echo htmlspecialchars($contract['customer_name']); ?></a></td>
                        <td><?php echo htmlspecialchars($contract['product_name']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($contract['loan_amount']); ?><br>
                            <font color="blue" style="font-weight: bold;" size="2"><?php echo number_format($contract['current_outstanding_principal']); ?></font>
                        </td>
                        <td><?php echo htmlspecialchars($contract['loan_date']); ?><br>-<?php echo htmlspecialchars($contract['maturity_date']); ?></td>
                        <td><?php echo htmlspecialchars($contract['agreement_date']); ?></td>
                        <td>
                            <font style="font-weight: bold;" size="2"><?php echo htmlspecialchars($contract['interest_rate']); ?>%</font><br><?php echo htmlspecialchars($contract['overdue_interest_rate']); ?>%
                        </td>
                        <td><?php echo htmlspecialchars($contract['last_collection_date'] ?? '-'); ?></td>
                        <td style="text-align: center; font-weight: bold; color: #000000; "><?php echo htmlspecialchars($contract['next_due_date'] ?? '-'); ?></td>
                        <td><?php echo get_status_display($contract['status']); ?></td>
                        <td class="action-buttons">
                            <a href="expected_interest_view.php?contract_id=<?php echo $contract['id']; ?>" class="btn btn-xs view_btn" onclick="return openPopupAndRefreshParent(this.href, '_blank', 'width=1400,height=800');">예상이자</a>
                            <button type="button" class="btn btn-xs memo-btn" data-contract-id="<?php echo $contract['id']; ?>" data-customer-id="<?php echo $contract['customer_id']; ?>"
                                data-customer-name="<?php echo htmlspecialchars($contract['customer_name']); ?>" data-customer-phone="<?php echo htmlspecialchars($contract['phone'] ?? ''); ?>">메모</button>
                            <a href="transaction_ledger.php?contract_id=<?php echo $contract['id']; ?>&mod=single" class="btn btn-xs view_btn" onclick="return openPopupAndRefreshParent(this.href, '_blank', 'width=1400,height=800');">원장</a>
                            <a href="sms.php?contract_id=<?php echo $contract['id']; ?>&customer_id=<?php echo $contract['customer_id']; ?>&mod=single" class="btn btn-xs view_btn" onclick="return openPopupAndRefreshParent(this.href, '_blank', 'width=1400,height=800');">SMS</a>
                            <?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
                                <a href="collection_manage.php?contract_id=<?php echo $contract['id']; ?>&mod=single" class="btn btn-xs add_btn" onclick="window.open(this.href, '_blank', 'width=1400,height=800'); return false;">입금</a>
                                <a href=" contract_manage.php?edit=<?php echo $contract['id']; ?>#add_contract_section" class="btn btn-xs edit_btn">수정</a>
                                <a href="../process/contract_process.php?delete=<?php echo $contract['id']; ?>" class="btn btn-xs del_btn" onclick="return confirm('정말 이 계약을 삭제하시겠습니까?');">삭제</a>
                                <a href="../process/reset_contract_data.php?id=<?php echo $contract['id']; ?>" class="btn btn-xs del_btn" onclick="if(!confirm('정말 이 계약을 초기화하시겠습니까?')) return false; window.open(this.href, 'reset_window', 'width=600,height=400'); return false;">초기화</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach;
            else: ?>
                <tr>
                    <td colspan="15" style="text-align: center;">검색된 계약이 없습니다.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Contract Section -->
<div id="add_contract_section" class="form-container" style="<?php if (!$customer_data && !$update) echo 'display:none;'; ?>">
    <h2 style="margin-top: 150px; font-weight: bold; text-transform: uppercase; text-decoration: underline;"><?php echo $update ? '계약 수정' : '신규 계약 추가'; ?></h2>

    <div id="search_area" <?php if ($update || $customer_data) echo 'style="display:none;"'; ?>>
        <div class="form-col">
            <label>고객 검색</label>
            <input type="text" id="customer_search" placeholder="계약할 고객의 이름, 고객번호, 연락처로 검색">
            <div id="search_results"></div>
        </div>
    </div>

    <div id="selected_customer_info" <?php if (!$customer_data) echo 'style="display:none;"'; ?>>
        <h4 style="margin-bottom: 10px;">선택된 고객 정보</h4>
        <div id="customer_details_box">
            <?php if ($customer_data): ?>
                <p>고객명 : <strong><span style="color: #0257f5ff; font-weight: bold; font-size: 1.2em"><?php echo htmlspecialchars($customer_data['name']); ?></span></strong></p>
                <p>주민번호 : <strong><span style="color: #0257f5ff; font-weight: bold; font-size: 1.2em"><?php echo htmlspecialchars($customer_data['resident_id_partial']); ?></span></strong></p>
                <p>연락처 : <strong><span style="color: #0257f5ff; font-weight: bold; font-size: 1.2em"><?php echo htmlspecialchars($customer_data['phone']); ?></span></strong></p>
                <hr style="margin: 10px 0;">
            <?php endif; ?>
        </div>
    </div>

    <form id="contract_form" action="../process/contract_process.php" method="post">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">

        <div class="form-grid">
            <div class="form-col"><label>상품명</label><select name="product_name" required><?php foreach ($products as $p): ?><option value="<?php echo $p; ?>" <?php if ($p == $product_name) echo 'selected'; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
            <div class="form-col"><label>약정일</label><select name="agreement_date" required><?php foreach ($due_days as $d): ?><option value="<?php echo $d; ?>" <?php if ($d == $agreement_date) echo 'selected'; ?>><?php echo $d; ?>일</option><?php endforeach; ?></select></div>
            <div class="form-col"><label>유예약정금</label><input type="number" name="deferred_agreement_amount" value="<?php echo $deferred_agreement_amount; ?>" placeholder="0"></div>
            <div class="form-col"><label>대출금액</label><input type="number" name="loan_amount" value="<?php echo $loan_amount; ?>" required></div>
            <div class="form-col"><label>대출일</label><input type="date" name="loan_date" value="<?php echo $loan_date; ?>" required></div>
            <div class="form-col"><label>만기일</label><input type="date" name="maturity_date" value="<?php echo $maturity_date; ?>" required></div>
            <div class="form-col"><label>대출금리(연)</label><input type="number" step="0.01" name="interest_rate" value="<?php echo $interest_rate; ?>" required></div>
            <div class="form-col"><label>연체금리(연)</label><input type="number" step="0.01" name="overdue_interest_rate" value="<?php echo $overdue_interest_rate; ?>" required></div>
            <?php if ($update): ?>
                <div class="form-col"><label>상태</label><select name="status"><?php foreach ($statuses as $k => $v): ?><option value="<?php echo $k; ?>" <?php if ($k == $status) echo 'selected'; ?>><?php echo $v; ?></option><?php endforeach; ?></select></div>
                <hr style="grid-column: 1 / -1; border-top: 1px solid #ccc; margin: 10px 0;">
                <div class="form-col"><label>이율 변경 기준일</label><input type="date" name="rate_change_date" value="<?php echo htmlspecialchars($rate_change_date ?? ''); ?>"></div>
                <div class="form-col"><label>변경-정상 이율(%)</label><input type="number" step="0.01" name="new_interest_rate" placeholder="변경 후 정상 이율" value="<?php echo htmlspecialchars($new_interest_rate ?? ''); ?>"></div>
                <div class="form-col"><label>변경-연체 이율(%)</label><input type="number" step="0.01" name="new_overdue_rate" placeholder="변경 후 연체 이율" value="<?php echo htmlspecialchars($new_overdue_rate ?? ''); ?>"></div>
                <div class="form-col"><label>다음 입금 예정일</label><input type="date" name="next_due_date" value="<?php echo htmlspecialchars($next_due_date ?? ''); ?>"></div>
            <?php endif; ?>
        </div>

        <div class="form-buttons" style="text-align: right; margin-top: 20px;">
            <button type="submit" name="<?php echo $update ? 'update' : 'save'; ?>" class="btn btn-primary"><?php echo $update ? '수정' : '저장'; ?></button>
            <a href="contract_manage.php" class="btn btn-secondary">취소</a>
        </div>
    </form>
</div>

<hr style="margin: 30px 0;">

<!-- Memo Modal -->
<div id="memoModal" class="modal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-shrink: 0;">
            <h3 id="memoModalTitle" style="margin: 0;">계약 메모</h3>
            <span class="close-button">&times;</span>
        </div>
        <div id="memoModalBody" class="memo-section">
            <!-- Memo content will be loaded here via AJAX -->
            <div class="memo-list" style="height: 100%;"></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const addContractSection = document.getElementById('add_contract_section');
        const showAddFormBtn = document.getElementById('show_add_form_btn');
        const selectAllCheckbox = document.getElementById('select_all_contracts');
        const contractCheckboxes = document.querySelectorAll('.contract_checkbox');
        const downloadSelectedBtn = document.getElementById('download_selected_btn');
        const memoModal = document.getElementById('memoModal');
        const memoModalCloseBtn = memoModal.querySelector('.close-button');
        const memoModalBody = memoModal.querySelector('#memoModalBody');

        // --- 신규 계약 추가 시, 연체금리 자동 계산 ---
        const isUpdateMode = <?php echo json_encode($update); ?>;
        if (!isUpdateMode) {
            const interestRateInput = document.querySelector('#contract_form input[name="interest_rate"]');
            const overdueInterestRateInput = document.querySelector('#contract_form input[name="overdue_interest_rate"]');

            if (interestRateInput && overdueInterestRateInput) {
                interestRateInput.addEventListener('input', function() {
                    const interestRate = parseFloat(this.value);
                    if (!isNaN(interestRate)) {
                        let overdueRate = interestRate + 3;
                        // 이자제한법상 최고금리 20% 초과 시 20%로 제한
                        if (overdueRate > 20) {
                            overdueRate = 20;
                        }
                        overdueInterestRateInput.value = overdueRate.toFixed(2);
                    }
                });
            }
        }


        // Show/hide add contract section
        if (showAddFormBtn) {
            showAddFormBtn.addEventListener('click', function() {
                addContractSection.style.display = 'block';
                addContractSection.scrollIntoView({
                    behavior: 'smooth'
                });
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('edit') || urlParams.has('customer_id')) {
            addContractSection.style.display = 'block';
        }

        // Select all contracts checkbox functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                contractCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }

        // Individual contract checkbox functionality
        if (contractCheckboxes) {
            contractCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked) {
                        selectAllCheckbox.checked = false;
                    } else {
                        const allChecked = Array.from(contractCheckboxes).every(cb => cb.checked);
                        selectAllCheckbox.checked = allChecked;
                    }
                });
            });
        }

        // Download selected contracts
        if (downloadSelectedBtn) {
            downloadSelectedBtn.addEventListener('click', function() {
                const selectedContractIds = Array.from(contractCheckboxes)
                    .filter(checkbox => checkbox.checked)
                    .map(checkbox => checkbox.value);

                if (selectedContractIds.length > 0) {
                    const queryParams = new URLSearchParams();
                    selectedContractIds.forEach(id => queryParams.append('contract_ids[]', id));
                    window.location.href = 'download_contracts.php?' + queryParams.toString();
                } else {
                    alert('다운로드할 계약을 선택해주세요.');
                }
            });
        }

        const runBatchBtn = document.getElementById('run_daily_batch_btn');
        if (runBatchBtn) {
            runBatchBtn.addEventListener('click', function() {
                if (!confirm('일일 배치 프로세스를 실행하시겠습니까? 모든 계약의 상태를 재계산하며, 시간이 걸릴 수 있습니다.')) {
                    return;
                }

                this.disabled = true;
                this.textContent = '배치 실행 중...';

                fetch('../process/daily_batch_process.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'action=run_batch' // To distinguish from GET requests
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) {
                            window.location.reload();
                        } else {
                            this.disabled = false;
                            this.textContent = '일일 배치 실행';
                        }
                    })
                    .catch(error => {
                        console.error('Batch process error:', error);
                        alert('배치 처리 중 오류가 발생했습니다. 개발자 콘솔을 확인해주세요.');
                        this.disabled = false;
                        this.textContent = '일일 배치 실행';
                    });
            });
        }

        // Memo Modal Logic
        document.querySelectorAll('.memo-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const contractId = this.dataset.contractId;
                const customerId = this.dataset.customerId;
                const customerName = this.dataset.customerName;

                document.getElementById('memoModalTitle').textContent = `계약 메모 - ${customerName}`;
                memoModal.style.display = 'block';

                // Load memos
                refreshMemoList(contractId, customerId);
            });
        });

        if (memoModalCloseBtn) {
            memoModalCloseBtn.onclick = function() {
                memoModal.style.display = 'none';
            }
        }

        // --- Bulk Classification Logic ---
        const bulkClassificationBtn = document.getElementById('bulk_classification_btn');
        const bulkClassificationModal = document.getElementById('bulkClassificationModal');
        const bulkClassificationCloseBtn = bulkClassificationModal.querySelector('.close-button');
        const confirmBulkClassificationBtn = document.getElementById('confirm_bulk_classification_btn');

        if (bulkClassificationBtn) {
            bulkClassificationBtn.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default button behavior
                const selectedCount = document.querySelectorAll('.contract_checkbox:checked').length;
                if (selectedCount === 0) {
                    alert('적용할 계약을 선택해주세요.');
                    return;
                }
                bulkClassificationModal.style.display = 'block';
            });
        }

        if (bulkClassificationCloseBtn) {
            bulkClassificationCloseBtn.onclick = () => {
                bulkClassificationModal.style.display = 'none';
            };
        }

        // Close modal when clicking outside
        window.addEventListener('click', (event) => {
            if (event.target == bulkClassificationModal) {
                bulkClassificationModal.style.display = 'none';
            }
            if (event.target == memoModal) {
                memoModal.style.display = 'none';
            }
        });

        if (confirmBulkClassificationBtn) {
            confirmBulkClassificationBtn.addEventListener('click', function() {
                const selectedContractIds = Array.from(document.querySelectorAll('.contract_checkbox:checked'))
                    .map(cb => cb.value);
                const codeId = document.getElementById('bulk_classification_select').value;
                const operation = document.querySelector('input[name="bulk_operation"]:checked').value;

                if (!codeId) {
                    alert('구분코드를 선택해주세요.');
                    return;
                }

                if (!confirm(`선택한 ${selectedContractIds.length}건의 계약에 대해 작업을 진행하시겠습니까?`)) return;

                const formData = new FormData();
                formData.append('action', 'bulk_update_classification');
                selectedContractIds.forEach(id => formData.append('contract_ids[]', id));
                formData.append('classification_code_id', codeId);
                formData.append('operation', operation);

                fetch('../process/contract_process.php', {
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
                    .catch(error => console.error('Error:', error));
            });
        }

    });

    // This function needs to be global to be called after AJAX content load
    function initializeMemoFormScripts(container) {
        const memoForm = container.querySelector('.memo-form');
        if (memoForm && !memoForm.dataset.initialized) {
            memoForm.dataset.initialized = 'true';

            // Use event delegation on the container for all interactions
            container.addEventListener('click', function(e) {
                // --- Edit Button ---
                if (e.target.classList.contains('edit-memo-btn')) {
                    const button = e.target;
                    memoForm.querySelector('input[name="memo_id"]').value = button.dataset.memoId;
                    memoForm.querySelector('textarea[name="memo_text"]').value = button.dataset.memoText;
                    memoForm.querySelector('select[name="color"]').value = button.dataset.memoColor;
                    memoForm.querySelector('.form-title').textContent = '메모 수정';
                    memoForm.querySelector('.cancel-edit-btn').style.display = 'inline-block';
                    memoForm.querySelector('textarea[name="memo_text"]').focus();
                }

                // --- Cancel Edit Button ---
                if (e.target.classList.contains('cancel-edit-btn')) {
                    const button = e.target;
                    memoForm.reset();
                    memoForm.querySelector('input[name="memo_id"]').value = '';
                    memoForm.querySelector('.form-title').textContent = '새 메모 작성';
                    button.style.display = 'none';
                }

            });

            // --- Form Submission (Save/Update) ---
            memoForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('save_memo', '1'); // Explicitly add save_memo action
                formData.append('ajax', '1');

                fetch('../process/memo_process.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const contractId = memoForm.querySelector('input[name="contract_id"]').value;
                            const customerId = memoForm.querySelector('input[name="customer_id"]').value;
                            refreshMemoList(contractId, customerId);
                        } else {
                            alert('오류: ' + (data.message || '메모 저장에 실패했습니다.'));
                        }
                    })
                    .catch(error => console.error('Error submitting memo form:', error));
            });
        }

        // --- Frequent Memo Select ---
        container.querySelectorAll('.frequent-memo-select').forEach(select => {
            select.addEventListener('change', function() {
                if (this.value) {
                    const textarea = this.closest('.memo-form').querySelector('textarea[name="memo_text"]');
                    textarea.value += (textarea.value ? '\n' : '') + this.value;
                    this.value = '';
                }
            });
        });
    }

    function refreshMemoList(contractId, customerId) {
        const memoModalBody = document.getElementById('memoModalBody');
        fetch(`../process/contract_process.php?action=get_memos&contract_id=${contractId}&customer_id=${customerId}`)
            .then(response => response.text())
            .then(html => {
                memoModalBody.innerHTML = html;
                initializeMemoFormScripts(memoModalBody);
            })
            .catch(error => console.error('Error refreshing memos:', error));
    }

    function openPopupAndRefreshParent(url, name, specs) {
        var win = window.open(url, name, specs);
        var interval = setInterval(function() {
            if (win.closed) {
                clearInterval(interval);
                window.location.reload();
            }
        }, 1000);
        return false;
    }
</script>

<!-- Pagination -->
<div class="pagination">
    <?php
    $range = 5;
    $query_params = $search_params;
    unset($query_params['page']);
    $base_url = http_build_query($query_params);

    if ($total_pages > 1) {
        echo "<a href='?page=1&$base_url'>&laquo;</a> ";

        $prev_page = max(1, $page - 1);
        echo "<a href='?page=$prev_page&$base_url'>&lsaquo;</a> ";

        for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++) {
            if ($i == $page) {
                echo "<strong>$i</strong> ";
            } else {
                echo "<a href='?page=$i&$base_url'>$i</a> ";
            }
        }

        $next_page = min($total_pages, $page + 1);
        echo "<a href='?page=$next_page&$base_url'>&rsaquo;</a> ";

        echo "<a href='?page=$total_pages&$base_url'>&raquo;</a>";
    }
    ?>
</div>

<?php include 'footer.php'; ?>