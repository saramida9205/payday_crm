<?php
require_once __DIR__ . '/../common.php';

function getContracts($link, $search_params) {
    $base_sql = "FROM contracts c JOIN customers cu ON c.customer_id = cu.id";
    
    $where_clauses = [];
    $params = [];
    $types = '';

    if (!empty($search_params['search'])) {
        $where_clauses[] = "(c.id LIKE ? OR cu.name LIKE ? OR c.customer_id LIKE ?)";
        $search_term = '%' . $search_params['search'] . '%';
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
        $types .= 'sss';
    }

    if (!empty($search_params['status'])) {
        $status = $search_params['status'];
        if ($status == 'valid') {
            $where_clauses[] = "c.status IN ('active', 'overdue')";
        } else {
            $where_clauses[] = "c.status = ?";
            $params[] = $status;
            $types .= 's';
        }
    }

    if (!empty($search_params['agreement_date'])) {
        $where_clauses[] = "c.agreement_date = ?";
        $params[] = $search_params['agreement_date'];
        $types .= 'i';
    }

    if (!empty($search_params['next_due_date'])) {
        $where_clauses[] = "c.next_due_date = ?";
        $params[] = $search_params['next_due_date'];
        $types .= 's';
    }

    if (!empty($search_params['loan_date_start'])) {
        $where_clauses[] = "c.loan_date >= ?";
        $params[] = $search_params['loan_date_start'];
        $types .= 's';
    }
    if (!empty($search_params['loan_date_end'])) {
        $where_clauses[] = "c.loan_date <= ?";
        $params[] = $search_params['loan_date_end'];
        $types .= 's';
    }

    // Classification Code Filter
    if (!empty($search_params['classification_codes'])) {
        $codes = $search_params['classification_codes'];
        // Ensure codes are integers to prevent SQL injection
        $codes = array_map('intval', $codes);
        $codes_str = implode(',', $codes);
        
        $logic = $search_params['classification_logic'] ?? 'OR'; // OR, AND, EXCLUDE

        if ($logic === 'EXCLUDE') {
            // Exclude contracts that have ANY of the selected codes
            $where_clauses[] = "c.id NOT IN (SELECT contract_id FROM contract_classifications WHERE classification_code_id IN ($codes_str))";
        } elseif ($logic === 'AND') {
            // Include contracts that have ALL of the selected codes
            foreach ($codes as $code_id) {
                $where_clauses[] = "c.id IN (SELECT contract_id FROM contract_classifications WHERE classification_code_id = $code_id)";
            }
        } else { // OR (Default)
            // Include contracts that have ANY of the selected codes
            $where_clauses[] = "c.id IN (SELECT contract_id FROM contract_classifications WHERE classification_code_id IN ($codes_str))";
        }
    }

    $where_sql = "";
    if (!empty($where_clauses)) {
        $where_sql = " WHERE " . implode(" AND ", $where_clauses);
    }

    // Count total records
    $count_sql = "SELECT COUNT(c.id) as total " . $base_sql . $where_sql;
    $stmt_count = mysqli_prepare($link, $count_sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_count);
    $total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
    mysqli_stmt_close($stmt_count);

    // Fetch paginated data
    // 정렬 로직을 SQL 쿼리에 통합
    $sort_map = [
        'id_desc' => 'c.id DESC',
        'id_asc' => 'c.id ASC',
        'loan_date_desc' => 'c.loan_date DESC',
        'loan_date_asc' => 'c.loan_date ASC',
    ];
    // 기본 정렬을 대출일 내림차순으로 설정
    $order_by_clause = $sort_map[$search_params['sort']] ?? 'c.loan_date DESC';

    $data_sql = "SELECT c.*, cu.name as customer_name, cu.phone, (SELECT MAX(collection_date) FROM collections WHERE contract_id = c.id AND deleted_at IS NULL) as last_collection_date " 
              . $base_sql 
              . $where_sql 
              . " ORDER BY " . $order_by_clause;
    $data_params = $params;
    $data_types = $types;

    if ($search_params['limit'] !== 'all') {
        $data_sql .= " LIMIT ?, ?";
        $data_params[] = ($search_params['page'] - 1) * $search_params['limit'];
        $data_params[] = $search_params['limit'];
        $data_types .= 'ii';
    }

    $stmt_data = mysqli_prepare($link, $data_sql);
    if (!empty($data_params)) {
        mysqli_stmt_bind_param($stmt_data, $data_types, ...$data_params);
    }
    mysqli_stmt_execute($stmt_data);
    $result = mysqli_stmt_get_result($stmt_data);
    $contracts = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_data);
    
    // Calculate summary data for all filtered contracts (not just paginated)
    $summary_sql = "SELECT 
                        SUM(c.loan_amount) as total_loan_amount,
                        SUM(c.current_outstanding_principal) as total_outstanding_balance,
                        COUNT(CASE WHEN c.status = 'overdue' THEN 1 END) as total_overdue_count,
                        SUM(CASE WHEN c.status = 'overdue' THEN c.current_outstanding_principal ELSE 0 END) as total_overdue_amount
                    " . $base_sql . $where_sql;
    $stmt_summary = mysqli_prepare($link, $summary_sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_summary, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_summary);
    $summary_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_summary));
    mysqli_stmt_close($stmt_summary);
    
    // 예상 이자는 여전히 개별 계산이 필요합니다.
    $all_filtered_contracts_sql = "SELECT c.id, c.interest_rate, c.overdue_interest_rate, c.next_due_date, c.last_interest_calc_date, c.loan_date, c.current_outstanding_principal " . $base_sql . $where_sql;
    $stmt_all_contracts = mysqli_prepare($link, $all_filtered_contracts_sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_all_contracts, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_all_contracts);
    $all_filtered_contracts_result = mysqli_stmt_get_result($stmt_all_contracts);

    $total_expected_interest = 0;
    while ($contract = mysqli_fetch_assoc($all_filtered_contracts_result)) {
        $interest_data = calculateAccruedInterest($link, $contract, $contract['next_due_date']);
        $total_expected_interest += $interest_data['total'];
    }
    mysqli_stmt_close($stmt_all_contracts);

    return array_merge(['data' => $contracts, 'total' => $total_records, 'total_expected_interest' => $total_expected_interest], $summary_data);
}

function getContractById($link, $id) {
    $sql = "SELECT * FROM contracts WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $contract = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $contract;
}

// --- SAVE NEW CONTRACT ---
if (isset($_POST['save'])) {
    $customer_id = (int)$_POST['customer_id'];
    $product_name = $_POST['product_name'];
    $agreement_date = (int)$_POST['agreement_date'];
    $loan_amount = $_POST['loan_amount'];
    $loan_date = $_POST['loan_date'];
    $maturity_date = $_POST['maturity_date'];
    $interest_rate = $_POST['interest_rate'];
    $overdue_interest_rate = $_POST['overdue_interest_rate'];
    $status = 'active'; // Initial status

    // Calculate the first due date
    $exceptions = getHolidayExceptions();
    $loan_date_obj = new DateTime($loan_date);
    $next_due_date_obj = get_next_due_date($loan_date_obj, $agreement_date, $exceptions);
    $next_due_date = $next_due_date_obj->format('Y-m-d');

    $deferred_agreement_amount = !empty($_POST['deferred_agreement_amount']) ? $_POST['deferred_agreement_amount'] : 0;

    $sql = "INSERT INTO contracts (customer_id, product_name, agreement_date, loan_amount, current_outstanding_principal, loan_date, maturity_date, interest_rate, overdue_interest_rate, status, next_due_date, deferred_agreement_amount) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "isisdssddssd", 
        $customer_id, $product_name, $agreement_date, $loan_amount, $loan_amount, $loan_date, 
        $maturity_date, $interest_rate, $overdue_interest_rate, $status, $next_due_date, $deferred_agreement_amount
    );

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "신규 계약이 성공적으로 생성되었습니다.";
        $new_contract_id = mysqli_insert_id($link);
        header('location: ../pages/contract_manage.php?edit=' . $new_contract_id);
    } else {
        $_SESSION['error_message'] = "계약 생성에 실패했습니다: " . mysqli_error($link);
        header('location: ../pages/contract_manage.php?customer_id=' . $customer_id);
    }
    mysqli_stmt_close($stmt);
    exit();
}

// --- UPDATE CONTRACT ---
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $product_name = $_POST['product_name'];
    $agreement_date = $_POST['agreement_date'];
    $loan_amount = $_POST['loan_amount'];
    $loan_date = $_POST['loan_date'];
    $maturity_date = $_POST['maturity_date'];
    $interest_rate = $_POST['interest_rate'];
    $overdue_interest_rate = $_POST['overdue_interest_rate'];
    $status = $_POST['status'];
    $rate_change_date = !empty($_POST['rate_change_date']) ? $_POST['rate_change_date'] : null;
    $new_interest_rate = !empty($_POST['new_interest_rate']) ? $_POST['new_interest_rate'] : null;
    $new_overdue_rate = !empty($_POST['new_overdue_rate']) ? $_POST['new_overdue_rate'] : null;
    $next_due_date = !empty($_POST['next_due_date']) ? $_POST['next_due_date'] : null;

    $deferred_agreement_amount = !empty($_POST['deferred_agreement_amount']) ? $_POST['deferred_agreement_amount'] : 0;

    $sql = "UPDATE contracts SET 
                product_name = ?, 
                agreement_date = ?, 
                loan_amount = ?, 
                loan_date = ?, 
                maturity_date = ?, 
                interest_rate = ?, 
                overdue_interest_rate = ?, 
                status = ?, 
                rate_change_date = ?, 
                new_interest_rate = ?, 
                new_overdue_rate = ?, 
                next_due_date = ?,
                deferred_agreement_amount = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "ssisssdsddssdi", 
        $product_name, $agreement_date, $loan_amount, $loan_date, $maturity_date, 
        $interest_rate, $overdue_interest_rate, $status, $rate_change_date, 
        $new_interest_rate, $new_overdue_rate, $next_due_date, $deferred_agreement_amount, $id
    );

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "계약 정보가 성공적으로 수정되었습니다.";
    } else {
        $_SESSION['error_message'] = "계약 정보 수정에 실패했습니다: " . mysqli_error($link);
    }
    mysqli_stmt_close($stmt);
    header('location: ../pages/contract_manage.php?edit=' . $id);
    exit();
}

// --- DELETE CONTRACT ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Start a transaction
    mysqli_begin_transaction($link);

    try {
        // Delete related records first (if any)
        $sql_delete_collections = "DELETE FROM collections WHERE contract_id = ?";
        $stmt_collections = mysqli_prepare($link, $sql_delete_collections);
        mysqli_stmt_bind_param($stmt_collections, "i", $id);
        mysqli_stmt_execute($stmt_collections);
        mysqli_stmt_close($stmt_collections);

        // After deleting related records, delete the contract
        $sql_delete_contract = "DELETE FROM contracts WHERE id = ?";
        $stmt_contract = mysqli_prepare($link, $sql_delete_contract);
        mysqli_stmt_bind_param($stmt_contract, "i", $id);
        
        if (mysqli_stmt_execute($stmt_contract)) {
            // Commit the transaction
            mysqli_commit($link);
            $_SESSION['message'] = "계약이 성공적으로 삭제되었습니다.";
        } else {
            // Rollback the transaction
            mysqli_rollback($link);
            $_SESSION['error_message'] = "계약 삭제에 실패했습니다: " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt_contract);

    } catch (Exception $e) {
        // Rollback the transaction on error
        mysqli_rollback($link);
        $_SESSION['error_message'] = "삭제 중 오류가 발생했습니다: " . $e->getMessage();
    }

    header('location: ../pages/contract_manage.php');
    exit();
}

if (isset($_GET['action']) && $_GET['action'] == 'get_memos') {
    $contract_id = (int)$_GET['contract_id'];
    $customer_id = (int)$_GET['customer_id'];

    // Fetch frequent memos
    $frequent_memos_query = mysqli_query($link, "SELECT * FROM frequent_memos ORDER BY id ASC");
    $frequent_memos = mysqli_fetch_all($frequent_memos_query, MYSQLI_ASSOC);
    $memo_colors = ['black' => '검정', 'red' => '빨강', 'blue' => '파랑', 'green' => '녹색', 'yellow' => '노랑', 'orange' => '오렌지', 'purple' => '보라'];

    $stmt_memo = mysqli_prepare($link, "SELECT m.*, e.name as employee_name FROM contract_memos m LEFT JOIN employees e ON m.created_by = e.username WHERE m.contract_id = ? ORDER BY m.created_at DESC");
    mysqli_stmt_bind_param($stmt_memo, "i", $contract_id);
    mysqli_stmt_execute($stmt_memo);
    $memos_result = mysqli_stmt_get_result($stmt_memo);
    $memos = mysqli_fetch_all($memos_result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_memo);

    // --- Start output buffering ---
    ob_start();
    ?>
    <div class="memo-list" id="memo-list-popup-<?php echo $contract_id; ?>">
        <?php if (empty($memos)): ?>
            <p class="no-memos">작성된 메모가 없습니다.</p>
        <?php else: foreach ($memos as $memo): ?>
            <div class="memo-item" style="border-left-color: <?php echo htmlspecialchars($memo['color']); ?>;">
                <div class="memo-actions">
                    <button type="button" class="btn btn-sm edit-memo-btn" data-memo-id="<?php echo $memo['id']; ?>" data-memo-text="<?php echo htmlspecialchars($memo['memo_text']); ?>" data-memo-color="<?php echo htmlspecialchars($memo['color']); ?>">수정</button>
                </div>
                <p class="memo-text"><?php echo nl2br(htmlspecialchars($memo['memo_text'])); ?></p>
                <p class="memo-meta"><strong>작성자:</strong> <?php echo htmlspecialchars($memo['employee_name'] ?? $memo['created_by']); ?> | <strong>작성일:</strong> <?php echo $memo['created_at']; ?><?php if($memo['updated_at']): ?> | <strong>수정일:</strong> <?php echo $memo['updated_at']; ?><?php endif; ?></p>
            </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="memo-form-container">
        <form action="../process/memo_process.php" method="post" class="memo-form">
            <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>">
            <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
            <input type="hidden" name="memo_id" value="">
            <h5><span class="form-title">새 메모 작성</span></h5>
            <div class="form-col"><textarea name="memo_text" rows="4" placeholder="메모 내용을 입력하세요..." required></textarea></div>
            <div class="form-grid memo-form-options">
                <div class="form-col">
                    <label>자주 쓰는 메모</label>
                    <select name="frequent_memo" class="frequent-memo-select">
                        <option value="">선택</option>
                        <?php foreach($frequent_memos as $fm): ?><option value="<?php echo htmlspecialchars($fm['memo_text']); ?>"><?php echo htmlspecialchars($fm['memo_text']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-col">
                    <label>색상</label>
                    <select name="color"><?php foreach($memo_colors as $color_val => $color_name): ?><option value="<?php echo $color_val; ?>"><?php echo $color_name; ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="form-buttons" style="text-align: right; margin-top: 15px;">
                <button type="submit" name="save_memo" class="btn btn-primary">메모 저장</button>
                <button type="button" class="btn btn-secondary cancel-edit-btn" style="display: none;">수정 취소</button>
            </div>
        </form>
    </div>
    <?php
    // --- End output buffering and echo content ---
    echo ob_get_clean();
    exit();
}

// Other functions related to contract processing...

// --- Bulk Classification Assignment ---
if (isset($_POST['action']) && $_POST['action'] === 'bulk_update_classification') {
    $contract_ids = $_POST['contract_ids'] ?? [];
    $classification_code_id = (int)$_POST['classification_code_id'];
    $operation = $_POST['operation']; // 'add' or 'remove'

    if (empty($contract_ids) || empty($classification_code_id)) {
        echo json_encode(['success' => false, 'message' => '계약이나 구분코드가 선택되지 않았습니다.']);
        exit;
    }

    $success_count = 0;
    foreach ($contract_ids as $contract_id) {
        $contract_id = (int)$contract_id;
        if ($operation === 'add') {
            if (add_contract_classification($link, $contract_id, $classification_code_id)) {
                $success_count++;
            }
        } elseif ($operation === 'remove') {
            if (remove_contract_classification($link, $contract_id, $classification_code_id)) {
                $success_count++;
            }
        }
    }

    echo json_encode(['success' => true, 'message' => "총 {$success_count}건의 계약에 대해 작업이 완료되었습니다."]);
    exit;
}

// --- Single Contract Classification Management (AJAX) ---
if (isset($_POST['action']) && $_POST['action'] === 'update_contract_classification') {
    $contract_id = (int)$_POST['contract_id'];
    $classification_code_id = (int)$_POST['classification_code_id'];
    $operation = $_POST['operation']; // 'add' or 'remove'

    if ($operation === 'add') {
        $result = add_contract_classification($link, $contract_id, $classification_code_id);
    } else {
        $result = remove_contract_classification($link, $contract_id, $classification_code_id);
    }

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB 오류: ' . mysqli_error($link)]);
    }
    exit;
}
?>