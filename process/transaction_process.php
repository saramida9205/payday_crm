<?php
require_once __DIR__ . '/../common.php';

function getWithdrawalDetails($link, $start_date, $end_date, $customer_name, $sort_order) {
    $sql = "SELECT 
                c.id as contract_id,
                c.customer_id,
                c.product_name,
                c.status,
                cu.name as customer_name,
                c.loan_date,
                c.maturity_date,
                c.agreement_date,
                c.next_due_date,
                c.interest_rate,
                c.overdue_interest_rate,
                c.shortfall_amount,
                (SELECT MAX(collection_date) FROM collections WHERE contract_id = c.id AND deleted_at IS NULL) as last_collection_date,
                c.loan_amount,
                cu.bank_name,
                cu.account_number
            FROM contracts c
            JOIN customers cu ON c.customer_id = cu.id
            WHERE 1=1";

    $params = [];
    $types = '';

    if (!empty($start_date)) {
        $sql .= " AND c.loan_date >= ?";
        $params[] = $start_date;
        $types .= 's';
    }
    if (!empty($end_date)) {
        $sql .= " AND c.loan_date <= ?";
        $params[] = $end_date;
        $types .= 's';
    }
    if (!empty($customer_name)) {
        $sql .= " AND cu.name LIKE ?";
        $params[] = '%' . $customer_name . '%';
        $types .= 's';
    }

    $sql .= " ORDER BY c.loan_date " . ($sort_order === 'asc' ? 'ASC' : 'DESC');

    $stmt = mysqli_prepare($link, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $details = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    // Calculate balance as of the search end date (or today)
    $as_of_date = !empty($end_date) ? $end_date : date('Y-m-d');
    foreach ($details as &$row) {
        $principal_paid = calculatePrincipalPaidAsOf($link, $row['contract_id'], $as_of_date);
        $row['balance_as_of'] = (float)$row['loan_amount'] - $principal_paid;
    }
    unset($row);

    return $details;
}

function getDepositDetails($link, $start_date, $end_date, $customer_name, $sort_order) {
    $sql = "SELECT 
                coll.id, coll.contract_id, coll.collection_date, coll.collection_type, coll.amount, coll.memo,
                coll.generated_interest, coll.transaction_id,
                con.agreement_date, con.loan_amount, con.loan_date,
                cust.id as customer_id, cust.name as customer_name
            FROM collections coll
            JOIN contracts con ON coll.contract_id = con.id
            JOIN customers cust ON con.customer_id = cust.id
            WHERE coll.deleted_at IS NULL";

    $params = [];
    $types = '';

    if (!empty($start_date)) {
        $sql .= " AND coll.collection_date >= ?";
        $params[] = $start_date;
        $types .= 's';
    }
    if (!empty($end_date)) {
        $sql .= " AND coll.collection_date <= ?";
        $params[] = $end_date;
        $types .= 's';
    }
    if (!empty($customer_name)) {
        $sql .= " AND cust.name LIKE ?";
        $params[] = '%' . $customer_name . '%';
        $types .= 's';
    }

    $sql .= " ORDER BY coll.collection_date " . ($sort_order === 'asc' ? 'ASC' : 'DESC') . ", coll.id DESC";

    $stmt = mysqli_prepare($link, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $raw_collections = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    // Grouping logic similar to collection_manage.php
    $grouped_collections = [];
    foreach ($raw_collections as $col) {
        $key = !empty($col['transaction_id']) ? $col['transaction_id'] : 'manual_' . $col['collection_date'] . '_' . $col['id'];
        if (!isset($grouped_collections[$key])) {
            $grouped_collections[$key] = array_merge($col, ['interest' => 0, 'principal' => 0, 'expense' => 0]);
        }
        if ($col['collection_type'] == '이자') $grouped_collections[$key]['interest'] += $col['amount'];
        elseif ($col['collection_type'] == '원금') $grouped_collections[$key]['principal'] += $col['amount'];
        elseif ($col['collection_type'] == '경비') $grouped_collections[$key]['expense'] += $col['amount'];
        $grouped_collections[$key]['generated_interest'] = max($grouped_collections[$key]['generated_interest'] ?? 0, (float)$col['generated_interest']);
    }

    // Calculate additional fields
    foreach ($grouped_collections as $key => &$row) {
        $row['total_deposit'] = $row['interest'] + $row['principal'] + $row['expense'];
        $is_manual_bulk = (strpos($row['memo'], '[수기일괄입금]') !== false);
        $row['shortfall'] = $is_manual_bulk ? $row['generated_interest'] : ($row['generated_interest'] - $row['interest']);

        // Get previous collection date
        $stmt_prev = mysqli_prepare($link, "SELECT MAX(collection_date) as prev_date FROM collections WHERE contract_id = ? AND collection_date < ? AND deleted_at IS NULL");
        mysqli_stmt_bind_param($stmt_prev, "is", $row['contract_id'], $row['collection_date']);
        mysqli_stmt_execute($stmt_prev);
        $prev_result = mysqli_stmt_get_result($stmt_prev);
        $row['previous_deposit_date'] = mysqli_fetch_assoc($prev_result)['prev_date'] ?? $row['loan_date'];
        mysqli_stmt_close($stmt_prev);

        // Get balance as of current deposit date
        $principal_paid_until = calculatePrincipalPaidAsOf($link, $row['contract_id'], $row['collection_date']);
        $row['balance_as_of'] = (float)$row['loan_amount'] - $principal_paid_until;
    }
    unset($row);

    return $grouped_collections;
}

?>