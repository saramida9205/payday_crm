<?php
require_once __DIR__ . '/../common.php';

header('Content-Type: application/json');

$search_term = isset($_GET['term']) ? $_GET['term'] : '';

if (empty($search_term)) {
    echo json_encode([]);
    exit;
}

$customers = [];
// 고객관리 검색과 동일하게 이름, 연락처, 고객번호로 검색
$sql = "SELECT id, name, resident_id_partial, phone, memo, address_registered, address_actual, bank_name, account_number FROM customers WHERE name LIKE ? OR phone LIKE ? OR CAST(id AS CHAR) LIKE ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    $search_param = "%" . $search_term . "%";
    mysqli_stmt_bind_param($stmt, "sss", $search_param, $search_param, $search_param);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $customers[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

echo json_encode($customers);
?>