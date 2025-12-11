<?php 
    include('header.php'); 
    include_once(__DIR__ . '/../common.php');

    // Get search parameters
    if (isset($_GET['start_date']) || isset($_GET['end_date']) || isset($_GET['customer_name']) || isset($_GET['contract_id'])) {
        // If any search parameter is set, use it.
        $search_start_date = $_GET['start_date'] ?? '';
        $search_end_date = $_GET['end_date'] ?? '';
    } else {
        // Otherwise, default to today's date.
        $search_start_date = date('Y-m-d');
        $search_end_date = date('Y-m-d');
    }
    $search_customer_name = isset($_GET['customer_name']) ? $_GET['customer_name'] : '';
    $search_contract_id = isset($_GET['contract_id']) ? $_GET['contract_id'] : '';

    // Get pre-fill parameters from expected interest view
    $prefill_collection_date = isset($_GET['collection_date']) ? $_GET['collection_date'] : '';
    $prefill_total_amount = isset($_GET['total_amount']) ? $_GET['total_amount'] : '';

    // Fetch collections and contracts
    $raw_collections = getCollections($link, $search_start_date, $search_end_date, $search_customer_name, $search_contract_id);
    $contracts = getActiveContractsForDropdown($link);
    $preselected_contract_id = isset($_GET['contract_id']) ? $_GET['contract_id'] : null;

    // Data processing for display...
    $grouped_collections = [];
    foreach ($raw_collections as $col) {
        $key = !empty($col['transaction_id']) ? $col['transaction_id'] : 'manual_' . $col['collection_date'] . '_' . $col['id']; // Align grouping with transaction_ledger.php
        if (!isset($grouped_collections[$key])) {
            $grouped_collections[$key] = array_merge($col, ['interest' => 0, 'principal' => 0, 'expense' => 0, 'shortfall' => 0, 'generated_interest' => 0, 'is_grouped' => !empty($col['transaction_id']), 'ids' => []]);
        }
        $grouped_collections[$key]['ids'][] = $col['id'];
        if ($col['collection_type'] == '이자') $grouped_collections[$key]['interest'] += $col['amount'];
        elseif ($col['collection_type'] == '원금') $grouped_collections[$key]['principal'] += $col['amount'];
        if ($col['collection_type'] == '경비') $grouped_collections[$key]['expense'] += $col['amount'];
        // Use the max generated_interest from the group, as other parts (like principal) will have 0. This prevents summing up the same value.
        $grouped_collections[$key]['generated_interest'] = max($grouped_collections[$key]['generated_interest'] ?? 0, (float)$col['generated_interest']);
    }

    // Find the last collection for each contract to enable deletion
    $last_collection_keys = [];
    foreach ($grouped_collections as $key => $collection) {
        $contract_id = $collection['contract_id'];
        if (!isset($last_collection_keys[$contract_id])) {
            $last_collection_keys[$contract_id] = $key;
        } else {
            $current_last_date = new DateTime($grouped_collections[$last_collection_keys[$contract_id]]['collection_date']);
            $new_date = new DateTime($collection['collection_date']);
            if ($new_date > $current_last_date) {
                $last_collection_keys[$contract_id] = $key;
            }
        }
    }
?>

<style>
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: #fefefe; margin: 10% auto; padding: 25px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
.modal-header h2 { margin-top: 0; }
.close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
.close-button:hover, .close-button:focus { color: black; text-decoration: none; cursor: pointer; }
.info-grid { display: grid; grid-template-columns: 150px 1fr; gap: 10px; margin-bottom: 20px; }
.info-grid strong { font-weight: 600; }
</style>

<h2>회수관리</h2>

<!-- Messages -->
<?php if (isset($_SESSION['message'])): ?>
<div class="msg"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
<div class="msg error-msg"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<!-- Auto-distribution Form -->
<?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
<h3>자동 분개 입금 처리</h3>
<div class="form-container">
    <form id="auto-distribute-form" method="post" action="../process/collection_process.php">
        <input type="hidden" name="action" value="save_collection">
        <div class="search-form-flex">
            <div class="form-col"><label>계약 선택</label>
                <select name="contract_id" id="contract_id_select" required>
                    <option value="">-- 계약을 선택하세요 --</option>
                    <?php foreach ($contracts as $contract): ?>
                    <option value="<?php echo $contract['id']; ?>" <?php if ($preselected_contract_id == $contract['id']) echo 'selected'; ?>><?php echo $contract['contract_info']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-col"><label>회수일</label><input type="date" name="collection_date" id="collection_date_input" value="<?php echo htmlspecialchars($prefill_collection_date); ?>" required></div>
            <div class="form-col"><label>총 회수액</label><input type="text" name="total_amount" id="total_amount_input" value="<?php echo htmlspecialchars($prefill_total_amount ? number_format((float)$prefill_total_amount) : ''); ?>" required oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');"></div>
            <div class="form-col"><label>메모</label><input type="text" name="memo" id="memo_input"></div>
            <div class="form-col"><button type="button" id="calculate_btn" class="btn btn-primary">계산하기</button></div>
        </div>
    </form>
</div>

<hr style="margin: 30px 0;">

<div style="display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
    <!-- 일괄 입금 업로드 섹션 -->
    <div style="flex: 1; min-width: 400px; max-width: 600px;">
        <h3>일괄 입금 업로드 (CSV/Excel)</h3>
        <div class="form-container">
            <p>CSV 파일을 업로드하여 여러 입금 내역을 한 번에 등록할 수 있습니다.</p>
            <p>파일 형식: <code>계약번호,입금일자(YYYY-MM-DD),총입금금액</code></p>
            <p><a href="../process/download_sample_csv.php" class="btn btn-secondary">샘플 CSV 다운로드</a></p>
            <form id="bulk_upload_form" action="../process/bulk_collection_process.php" method="post" enctype="multipart/form-data">
                <div class="input-group">
                    <label for="bulk_upload_file">업로드할 파일 선택:</label>
                    <input type="file" name="bulk_upload_file" id="bulk_upload_file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                </div>
                <button type="submit" name="upload_bulk_collections" class="btn btn-primary">파일 업로드</button>
            </form>
            <div id="upload_progress_container" style="display:none; margin-top: 20px;">
                <p id="upload_status_message"></p>
            </div>
        </div>
    </div>

    <!-- 수기계산 일괄입금 업로드 섹션 -->
    <div style="flex: 1; min-width: 400px; max-width: 600px;">
        <h3>수기계산 일괄입금 업로드</h3>
        <div class="form-container">
            <p>시스템 계산과 상관없이, 수기로 계산된 값을 그대로 입금 처리합니다. (과거 데이터 이관용)</p>
            <p>파일 형식: <code>계약번호,입금일자,입금액,이자상환금액,부족금발생금액,원금상환금액,메모</code></p>
            <p><a href="../process/download_sample_csv.php?type=manual" class="btn btn-secondary">수기계산 샘플 CSV 다운로드</a></p>
            <form id="manual_bulk_upload_form" action="../process/manual_bulk_collection_process.php" method="post" enctype="multipart/form-data">
                <div class="input-group">
                    <label for="manual_bulk_upload_file">업로드할 파일 선택:</label>
                    <input type="file" name="manual_bulk_upload_file" id="manual_bulk_upload_file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                </div>
                <button type="submit" name="upload_manual_bulk_collections" class="btn btn-primary">수기계산 파일 업로드</button>
            </form>
        </div>
    </div>
</div>

<div id="manual_upload_progress_container" style="display:none; margin-top: 20px;">
    <p id="manual_upload_status_message"></p>
</div>
<?php endif; ?>

<!-- Confirmation Modal -->
<div id="confirmation-modal" class="modal">
    <div class="modal-content form-container">
        <div class="modal-header" style="position: relative;">
            <span class="close-button" style="position: absolute; top: 10px; right: 10px; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            <h2 style="text-align: center; margin-bottom: 20px;">자동 분개 확인 및 수정</h2>
        </div>
        <form id="final-save-form" method="post" action="../process/collection_process.php">
            <input type="hidden" name="action" value="save_collection">
            <input type="hidden" name="contract_id" id="modal_contract_id">
            <input type="hidden" name="collection_date" id="modal_collection_date">
            <input type="hidden" name="memo" id="modal_memo">
            <input type="hidden" name="total_amount" id="modal_total_amount_hidden">

            <div class="info-grid" style="margin-bottom: 20px;">
                <div class="info-item"><strong>현재 대출잔액:</strong> <span id="modal_outstanding_principal"></span></div>
                <div class="info-item"><strong>이자 계산기간:</strong> <span id="modal_interest_period"></span></div>
                <div class="info-item"><strong>계산된 총 이자:</strong> <span id="modal_accrued_interest_display"></span></div>
                <div class="info-item"><strong>입력된 총 회수액:</strong> <span id="modal_total_amount"></span></div>
                <div class="info-item"><strong>미수 비용:</strong> <span id="modal_unpaid_expenses" style="color: #d9534f;"></span></div>
                <div class="info-item"><strong>기존 부족금:</b> <span id="modal_existing_shortfall"></span></div>
                <div class="info-item"><strong>예상 부족금:</strong> <span id="modal_expected_new_shortfall"></span></div>
            </div>
            <hr style="margin: 20px 0;">
            <div class="form-col">
                <label for="modal_expense_payment">선처리될 경비</label>
                <input type="text" name="expense_payment" id="modal_expense_payment" class="form-control" value="0" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');">
            </div>
            <div class="form-col">
                <label for="modal_expense_memo">경비 메모</label>
                <input type="text" name="expense_memo" id="modal_expense_memo" class="form-control">
            </div>
            <div class="form-col">
                <label for="modal_interest_payment">상환될 이자/부족금</label>
                <input type="text" name="interest_payment" id="modal_interest_payment" class="form-control" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');">
            </div>
            <div class="form-col">
                <label for="modal_principal_payment">상환될 원금</label>
                <input type="text" name="principal_payment" id="modal_principal_payment" class="form-control" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');">
            </div>
            <div class="form-buttons" style="text-align: right; margin-top: 20px;">
                <button type="button" id="recalculate_btn" class="btn btn-secondary">재계산</button>
                <button type="submit" name="save_collection" class="btn btn-primary">최종 저장</button>
            </div>
        </form>
    </div>
</div>

<hr style="margin: 30px 0;">

<h3>회수 내역 목록</h3>
<!-- Search Form -->
<form action="collection_manage.php" method="get" class="form-container">
    <div class="search-form-flex">
        <div class="form-col"><label>입금일 (시작)</label><input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($search_start_date); ?>"></div>
        <div class="form-col"><label>입금일 (종료)</label>
            <div style="display: flex; align-items: center; gap: 10px;">
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($search_end_date); ?>">
                <input type="checkbox" id="today_checkbox" <?php echo ($search_start_date == date('Y-m-d') && $search_end_date == date('Y-m-d')) ? 'checked' : ''; ?>><label for="today_checkbox" style="white-space: nowrap;">오늘</label>
            </div>
        </div>
        <div class="form-col"><label>고객명</label><input type="text" name="customer_name" placeholder="고객명" value="<?php echo htmlspecialchars($search_customer_name); ?>"></div>
        <div class="form-col"><label>계약번호</label><input type="text" name="contract_id" placeholder="계약번호" value="<?php echo htmlspecialchars($search_contract_id); ?>"></div>
        <div class="form-col"><button type="submit" class="btn btn-primary">검색</button></div>
    </div>
</form>

<div style="margin-bottom: 10px;">
    <?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
    <button type="button" id="delete_selected_collections" class="btn btn-danger">선택 항목 삭제</button>
    <?php endif; ?>
    <a href="collection_trash.php" class="btn btn-secondary">휴지통</a>
</div>

<!-- Collections Table -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th><input type="checkbox" id="select_all_collections"></th>
                <th>계약번호</th>
                <th>입금일자</th>
                <th>고객명</th>
                <th>이자</th>
                <th>원금</th>
                <th>경비</th>
                <th>입금합계</th>
                <th>부족금액</th>
                <th>메모</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($grouped_collections as $key => $row): ?>
            <tr>
                <td><input type="checkbox" class="collection_checkbox" value="<?php echo implode(',', $row['ids']); ?>"></td>
                <td><?php echo htmlspecialchars($row['contract_id']); ?></td>
                <td><?php echo htmlspecialchars($row['collection_date']); ?></td>
                <td><a href="customer_detail.php?id=<?php echo $row['customer_id']; ?>"><?php echo htmlspecialchars($row['customer_name']); ?></a></td>
                <td style="text-align: right;"><?php echo number_format($row['interest']); ?></td>
                <td style="text-align: right;"><?php echo number_format($row['principal']); ?></td>
                <td style="text-align: right;"><?php echo number_format($row['expense']); ?></td>
                <td style="text-align: right; font-weight: bold;"><?php echo number_format($row['interest'] + $row['principal'] + $row['expense']); ?></td>
                <td style="text-align: right; color: #dc3545;">
                    <?php 
                        $is_manual_bulk = (strpos($row['memo'], '[수기일괄입금]') !== false);
                        // For manual bulk entries, the generated_interest IS the final shortfall. Do not calculate.
                        $shortfall_display = $is_manual_bulk ? $row['generated_interest'] : ($row['generated_interest'] - $row['interest']);
                        echo number_format($shortfall_display);
                    ?>
                </td>
                <td><?php echo htmlspecialchars($row['memo']); ?></td>
                <td>
                    <?php 
                        $is_last_collection = (isset($last_collection_keys[$row['contract_id']]) && $last_collection_keys[$row['contract_id']] === $key);
                        $current_search_params = [
                            'start_date' => $search_start_date,
                            'end_date' => $search_end_date,
                            'customer_name' => $search_customer_name,
                            'contract_id' => $search_contract_id
                        ];
                        $query_string = http_build_query($current_search_params);
                        $delete_link = "javascript:void(0);"; // Use javascript for POST submission
                        $confirm_message = "정말로 이 입금 내역을 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.";
                    ?>
                    <?php if (isset($_SESSION['permission_level']) && $_SESSION['permission_level'] == 0): ?>
                    <a href="<?php echo $delete_link; ?>"
                       class="btn btn-sm delete_single_collection <?php echo $is_last_collection ? 'btn-danger' : 'btn-secondary'; ?>"
                       style="padding: 2px 5px; font-size: 11px;"
                       data-ids="<?php echo implode(',', $row['ids']); ?>"
                       <?php if (!$is_last_collection) echo 'disabled aria-disabled="true"'; ?>>
                       삭제
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Helper Functions for Number Formatting ---
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function cleanNumber(str) {
        return parseFloat(str.replace(/,/g, '')) || 0;
    }

    // --- Today Checkbox --- 
    const todayCheckbox = document.getElementById('today_checkbox');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    todayCheckbox.addEventListener('change', function() {
        const today = new Date().toISOString().slice(0, 10);
        if (this.checked) {
            startDateInput.value = today;
            endDateInput.value = today;
        } else {
            startDateInput.value = '';
            endDateInput.value = '';
        }
    });

    // --- Modal Logic --- 
    const modal = document.getElementById('confirmation-modal');
    const calculateBtn = document.getElementById('calculate_btn');
    const closeBtn = document.querySelector('.close-button');
    const recalculateBtn = document.getElementById('recalculate_btn');
    const finalSaveForm = document.getElementById('final-save-form');
    let calculationData = {}; // Variable to store data from server

    calculateBtn.addEventListener('click', function() {
        const form = document.getElementById('auto-distribute-form');
        const formData = new FormData(form);
        
        // Clean the total amount before sending
        const rawTotalAmount = cleanNumber(document.getElementById('total_amount_input').value);
        formData.set('total_amount', rawTotalAmount);
        formData.append('action', 'calculate');

        fetch('../process/collection_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                calculationData = data; // Store the whole response
                const totalAmount = rawTotalAmount;
                
                // Populate modal fields
                document.getElementById('modal_contract_id').value = document.getElementById('contract_id_select').value;
                document.getElementById('modal_collection_date').value = document.getElementById('collection_date_input').value;
                document.getElementById('modal_memo').value = document.getElementById('memo_input').value;
                document.getElementById('modal_total_amount_hidden').value = totalAmount;

                document.getElementById('modal_outstanding_principal').textContent = formatNumber(data.outstanding_principal) + ' 원';
                document.getElementById('modal_interest_period').textContent = `${data.interest_period_start} 부터 (${data.interest_period_days}일간)`;
                document.getElementById('modal_accrued_interest_display').textContent = formatNumber(data.accrued_interest) + ' 원';
                document.getElementById('modal_total_amount').textContent = formatNumber(totalAmount) + ' 원';
                document.getElementById('modal_unpaid_expenses').textContent = formatNumber(data.unpaid_expenses) + ' 원';
                document.getElementById('modal_existing_shortfall').textContent = formatNumber(data.shortfall_amount) + ' 원';
                document.getElementById('modal_expected_new_shortfall').textContent = formatNumber(data.expected_new_shortfall) + ' 원';
                
                document.getElementById('modal_expense_payment').value = 0; // Reset expense field
                document.getElementById('modal_expense_memo').value = ''; // Reset expense memo field
                document.getElementById('modal_interest_payment').value = formatNumber(data.interest_payment);
                document.getElementById('modal_principal_payment').value = formatNumber(data.principal_payment);

                modal.style.display = 'block';
            } else {
                alert('계산 오류: ' + data.message);
            }
        })
        .catch(error => alert('오류가 발생했습니다: ' + error));
    });

    recalculateBtn.addEventListener('click', function() {
        const totalAmount = parseFloat(document.getElementById('modal_total_amount_hidden').value) || 0;
        const expensePayment = cleanNumber(document.getElementById('modal_expense_payment').value);

        if (expensePayment > totalAmount) {
            alert('경비는 총 회수액을 초과할 수 없습니다.');
            document.getElementById('modal_expense_payment').value = formatNumber(totalAmount);
            // We can either trigger recalculation again or just set the value.
            // For simplicity, we'll just cap it and let the user click again if needed.
            return;
        }

        const amountAfterExpense = totalAmount - expensePayment;
        const totalDueInterestAndShortfall = (calculationData.accrued_interest || 0) + (calculationData.shortfall_amount || 0);
        
        const interestPayment = Math.min(amountAfterExpense, totalDueInterestAndShortfall);
        const principalPayment = Math.min(amountAfterExpense - interestPayment, (calculationData.outstanding_principal || 0));

        const newShortfall = totalDueInterestAndShortfall - interestPayment;
        
        document.getElementById('modal_interest_payment').value = formatNumber(Math.round(interestPayment));
        document.getElementById('modal_principal_payment').value = formatNumber(Math.round(principalPayment));
        document.getElementById('modal_expected_new_shortfall').textContent = formatNumber(Math.round(newShortfall)) + ' 원';

        // Final check to prevent overallocation due to rounding
        const finalAllocated = expensePayment + Math.round(interestPayment) + Math.round(principalPayment);
        if (finalAllocated > totalAmount) {
            const overage = finalAllocated - totalAmount;
            document.getElementById('modal_principal_payment').value = formatNumber(Math.round(principalPayment - overage));
        }
    });

    // Clean inputs before final submission
    finalSaveForm.addEventListener('submit', function(e) {
        // We need to remove commas from the inputs so the backend receives clean numbers
        const expenseInput = document.getElementById('modal_expense_payment');
        const interestInput = document.getElementById('modal_interest_payment');
        const principalInput = document.getElementById('modal_principal_payment');

        expenseInput.value = cleanNumber(expenseInput.value);
        interestInput.value = cleanNumber(interestInput.value);
        principalInput.value = cleanNumber(principalInput.value);
        
        // Note: If submission fails/prevented, we might want to restore commas, 
        // but for a standard submit, the page will reload anyway.
    });

    closeBtn.onclick = function() { modal.style.display = "none"; }
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
    // --- Select All Checkbox ---
    const selectAllCheckbox = document.getElementById('select_all_collections');
    const collectionCheckboxes = document.querySelectorAll('.collection_checkbox');

    selectAllCheckbox.addEventListener('change', function() {
        collectionCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    collectionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!this.checked) {
                selectAllCheckbox.checked = false;
            } else {
                const allChecked = Array.from(collectionCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            }
        });
    });

    // --- Individual Delete ---
    document.querySelectorAll('.delete_single_collection').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            if (this.hasAttribute('disabled')) return;

            const collectionIds = this.dataset.ids;
            if (confirm('정말로 이 입금 내역을 삭제하시겠습니까?')) {
                fetch('../process/collection_process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_single&ids=' + collectionIds // Use delete_single for clarity
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.href = location.pathname + location.search;
                    } else {
                        alert('삭제 실패: ' + data.message);
                    }
                })
                .catch(error => alert('오류 발생: ' + error));
            }
        });
    });

    // --- Bulk Delete ---
    document.getElementById('delete_selected_collections').addEventListener('click', function() {
        const selectedIds = Array.from(document.querySelectorAll('.collection_checkbox:checked'))
                               .map(checkbox => checkbox.value);

        if (selectedIds.length === 0) {
            alert('삭제할 입금 내역을 선택해주세요.');
            return;
        }

        if (confirm('선택된 ' + selectedIds.length + '개의 입금 내역을 정말로 삭제하시겠습니까?')) {
            fetch('../process/collection_process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=delete_bulk&ids=' + selectedIds.join(',')
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.href = location.pathname + location.search;
                } else {
                    alert('일괄 삭제 실패: ' + data.message);
                }
            })
            .catch(error => alert('오류 발생: ' + error));
        }
    });

    // --- Bulk Upload ---
    const bulkUploadForm = document.getElementById('bulk_upload_form');
    const uploadProgressContainer = document.getElementById('upload_progress_container');
    const uploadStatusMessage = document.getElementById('upload_status_message');

    bulkUploadForm.addEventListener('submit', function(e) {
        uploadStatusMessage.textContent = '작업중... 잠시 기다려주세요.';
        uploadProgressContainer.style.display = 'block';
    });

    const manualBulkUploadForm = document.getElementById('manual_bulk_upload_form');
    manualBulkUploadForm.addEventListener('submit', function(e) {
        // Use the same progress indicator for simplicity
        const manualUploadStatusMessage = document.getElementById('manual_upload_status_message');
        manualUploadStatusMessage.textContent = '수기계산 데이터 처리중... 잠시 기다려주세요.';
        document.getElementById('manual_upload_progress_container').style.display = 'block';
    });

    // --- Prevent Past Date Entry ---
    const contractSelect = document.getElementById('contract_id_select');
    const collectionDateInput = document.getElementById('collection_date_input');

    contractSelect.addEventListener('change', function() {
        const contractId = this.value;
        if (!contractId) {
            collectionDateInput.min = ''; // 계약이 선택되지 않으면 min 속성 제거
            return;
        }

        // Fetch the last collection date for the selected contract
        fetch(`../process/collection_process.php?action=get_last_collection_date&contract_id=${contractId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.last_collection_date) {
                    // Set the minimum selectable date to the day after the last collection
                    let lastDate = new Date(data.last_collection_date);
                    lastDate.setDate(lastDate.getDate() + 1);
                    collectionDateInput.min = lastDate.toISOString().split('T')[0];
                } else {
                    // If no last collection date, fetch the loan start date
                    fetch(`../process/contract_process.php?action=get_loan_start_date&contract_id=${contractId}`)
                        .then(res => res.json())
                        .then(cData => {
                            if(cData.success && cData.loan_start_date) {
                                collectionDateInput.min = cData.loan_start_date;
                            } else {
                                collectionDateInput.min = ''; // Fallback
                            }
                        });
                }
            })
            .catch(error => {
                console.error('Error fetching last collection date:', error);
                collectionDateInput.min = ''; // Clear restriction on error
            });
    });

    // Trigger the change event if a contract is already selected on page load
    if (contractSelect.value) {
        contractSelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php include 'footer.php'; ?>