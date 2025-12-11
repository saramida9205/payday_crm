<?php
require_once __DIR__ . '/../common.php';

// Function to get all customers, with optional search
function getCustomers($link, $search_term = '', $page = 1, $limit = 20) {
    $page = max(1, (int)$page);
    $limit = max(1, (int)$limit);
    $offset = ($page - 1) * $limit;

    $params = [];
    $types = '';
    $where_clause = "";

    if (!empty($search_term)) {
        // Full-Text Search for performance. Assumes a FULLTEXT index exists on (name, phone).
        // ALTER TABLE customers ADD FULLTEXT(name, phone);
        $where_clause = " WHERE MATCH(name, phone) AGAINST(? IN BOOLEAN MODE)";
        $params[] = $search_term . '*'; // Add wildcard for partial matching
        $types .= 's';
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM customers" . $where_clause;
    $stmt_count = mysqli_prepare($link, $count_sql);
    if (!empty($search_term)) {
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_count);
    $count_result = mysqli_stmt_get_result($stmt_count);
    $total_records = mysqli_fetch_row($count_result)[0];
    mysqli_stmt_close($stmt_count);

    // Get paginated results
    $sql = "SELECT * FROM customers" . $where_clause . " ORDER BY id DESC LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($link, $sql);

    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
    mysqli_stmt_bind_param($stmt, $types, ...$params);

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return ['data' => $data, 'total' => $total_records];
}

// Function to get a single customer by ID
function getCustomerById($link, $id) {
    $id = (int)$id;
    $sql = "SELECT * FROM customers WHERE id = ? LIMIT 1";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            return mysqli_fetch_assoc($result);
        }
    }
    return null;
}

// Initialize variables
$name = $resident_id_partial = $phone = $address_registered = $address_actual = $memo = $application_source = $requested_loan_amount = $loan_application_date = $manager = $bank_name = $account_number = "";
$update = false;
$id = 0;

// Add Customer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {
    $name = trim($_POST["name"]);
    $phone = trim($_POST["phone"]);
    $resident_id_partial = trim($_POST["resident_id_partial"]);
    $address_registered = trim($_POST["address_registered"]);
    $address_actual = trim($_POST["address_actual"]);
    $memo = trim($_POST["memo"]);
    $application_source = trim($_POST["application_source"]);
    $requested_loan_amount = trim($_POST["requested_loan_amount"]);
    $loan_application_date = trim($_POST["loan_application_date"]);
    $manager = trim($_POST["manager"]);
    $bank_name = !empty(trim($_POST["bank_name"])) ? trim($_POST["bank_name"]) : '우리은행';
    $account_number = !empty(trim($_POST["account_number"])) ? trim($_POST["account_number"]) : '1005-380-207056 (주)페이데이캐피탈대부';
    
    $sql = "INSERT INTO customers (name, resident_id_partial, phone, address_registered, address_actual, memo, application_source, requested_loan_amount, loan_application_date, manager, bank_name, account_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ssssssssssss", $name, $resident_id_partial, $phone, $address_registered, $address_actual, $memo, $application_source, $requested_loan_amount, $loan_application_date, $manager, $bank_name, $account_number);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "고객이 성공적으로 추가되었습니다.";
        } else {
            $_SESSION['error_message'] = "오류가 발생했습니다. 잠시 후 다시 시도해주세요.";
        }
        mysqli_stmt_close($stmt);
    }
    header("location: ../pages/customer_manage.php");
    exit();
}

// Delete Customer
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $check_sql = "SELECT COUNT(*) as contract_count FROM contracts WHERE customer_id = ? AND status IN ('active', 'overdue')";
    if ($stmt = mysqli_prepare($link, $check_sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row['contract_count'] > 0) {
            $_SESSION['error_message'] = "진행중인 계약이 있는 고객은 삭제할 수 없습니다. 계약을 먼저 완납 또는 부실 처리해주세요.";
        } else {
            $delete_sql = "DELETE FROM customers WHERE id = ?";
            if ($del_stmt = mysqli_prepare($link, $delete_sql)) {
                mysqli_stmt_bind_param($del_stmt, "i", $id);
                if (mysqli_stmt_execute($del_stmt)) {
                    $_SESSION['message'] = "고객 정보가 삭제되었습니다.";
                } else {
                    $_SESSION['error_message'] = "고객 삭제 중 오류가 발생했습니다.";
                }
                mysqli_stmt_close($del_stmt);
            }
        }
    } else {
        $_SESSION['error_message'] = "오류가 발생했습니다. 잠시 후 다시 시도해주세요.";
    }
    
    header("location: ../pages/customer_manage.php");
    exit();
}

// Get Customer for editing
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $update = true;
    $n = getCustomerById($link, $id); // Use prepared statement function

    if ($n) {
        $name = $n['name'];
        $resident_id_partial = $n['resident_id_partial'];
        $phone = $n['phone'];
        $address_registered = $n['address_registered'];
        $address_actual = $n['address_actual'];
        $memo = $n['memo'];
        $application_source = $n['application_source'];
        $requested_loan_amount = $n['requested_loan_amount'];
        $loan_application_date = $n['loan_application_date'];
        $manager = $n['manager'];
        $bank_name = $n['bank_name'];
        $account_number = $n['account_number'];
    }
}

// Update Customer
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $resident_id_partial = $_POST['resident_id_partial'];
    $phone = $_POST['phone'];
    $address_registered = $_POST['address_registered'];
    $address_actual = $_POST['address_actual'];
    $memo = $_POST['memo'];
    $application_source = $_POST['application_source'];
    $requested_loan_amount = $_POST['requested_loan_amount'];
    $loan_application_date = $_POST['loan_application_date'];
    $manager = $_POST['manager'];
    $bank_name = !empty(trim($_POST["bank_name"])) ? trim($_POST["bank_name"]) : '우리은행';
    $account_number = !empty(trim($_POST["account_number"])) ? trim($_POST["account_number"]) : '1005-380-207056 (주)페이데이캐피탈대부';

    $sql = "UPDATE customers SET name=?, resident_id_partial=?, phone=?, address_registered=?, address_actual=?, memo=?, application_source=?, requested_loan_amount=?, loan_application_date=?, manager=?, bank_name=?, account_number=? WHERE id=?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ssssssssssssi", $name, $resident_id_partial, $phone, $address_registered, $address_actual, $memo, $application_source, $requested_loan_amount, $loan_application_date, $manager, $bank_name, $account_number, $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "고객 정보가 성공적으로 수정되었습니다.";
        } else {
            $_SESSION['error_message'] = "오류가 발생했습니다. 잠시 후 다시 시도해주세요.";
        }
        mysqli_stmt_close($stmt);
    }
    header("location: ../pages/customer_manage.php");
    exit();
}
?>