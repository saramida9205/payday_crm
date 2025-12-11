<?php
require_once __DIR__ . '/../common.php';

header('Content-Type: application/json');

$term = $_GET['term'] ?? '';

if (empty($term)) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT 
            c.id as contract_id, 
            cu.name as customer_name, 
            c.loan_amount,
            c.loan_date
        FROM contracts c
        JOIN customers cu ON c.customer_id = cu.id
        WHERE (cu.name LIKE ? OR CAST(c.id AS CHAR) LIKE ?)
        ORDER BY cu.name ASC, c.id DESC
        LIMIT 10";

$contracts = [];
if ($stmt = mysqli_prepare($link, $sql)) {
    $search_param = "%" . $term . "%";
    mysqli_stmt_bind_param($stmt, "ss", $search_param, $search_param);
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $contracts = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

echo json_encode($contracts);