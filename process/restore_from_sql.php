<?php
require_once __DIR__ . '/../common.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check for admin permission
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    $response['message'] = '복원 권한이 없습니다.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = '잘못된 요청 방식입니다.';
    echo json_encode($response);
    exit;
}

global $link;

$new_db_name = $_POST['new_db_name'] ?? '';

try {
    // --- 1. Validate Inputs ---
    if (empty($new_db_name)) {
        throw new Exception('새 데이터베이스 이름이 지정되지 않았습니다.');
    }
    if (strpos($new_db_name, DB_NAME . '_') !== 0) {
        throw new Exception('새 데이터베이스 이름은 "' . DB_NAME . '_"로 시작해야 합니다.');
    }
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('SQL 파일 업로드에 실패했습니다. 오류 코드: ' . ($_FILES['sql_file']['error'] ?? 'N/A'));
    }
    $file_path = $_FILES['sql_file']['tmp_name'];
    if (pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION) !== 'sql') {
        throw new Exception('유효하지 않은 파일 형식입니다. .sql 파일만 업로드할 수 있습니다.');
    }

    set_time_limit(0);
    ini_set('memory_limit', '1024M');

    // --- 2. Check if DB already exists ---
    $check_db_sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?";
    $stmt_check = mysqli_prepare($link, $check_db_sql);
    mysqli_stmt_bind_param($stmt_check, "s", $new_db_name);
    mysqli_stmt_execute($stmt_check);
    if (mysqli_stmt_fetch($stmt_check)) {
        throw new Exception("데이터베이스 '{$new_db_name}'가 이미 존재합니다.");
    }
    mysqli_stmt_close($stmt_check);

    // --- 3. Create new DB ---
    if (!mysqli_query($link, "CREATE DATABASE `{$new_db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        throw new Exception("데이터베이스 '{$new_db_name}' 생성에 실패했습니다: " . mysqli_error($link));
    }

    // --- 4. Select the new DB and execute SQL file ---
    mysqli_select_db($link, $new_db_name);

    $sql_content = file_get_contents($file_path);
    if ($sql_content === false) {
        throw new Exception('SQL 파일을 읽는 데 실패했습니다.');
    }

    // Execute multi-query
    if (mysqli_multi_query($link, $sql_content)) {
        // Clear all results from the multi-query
        do {
            if ($result = mysqli_store_result($link)) {
                mysqli_free_result($result);
            }
        } while (mysqli_next_result($link));
    }

    // Check for errors after multi-query
    if (mysqli_errno($link)) {
        throw new Exception("SQL 실행 중 오류 발생: " . mysqli_error($link));
    }

    $response['success'] = true;
    $response['message'] = "SQL 파일로부터 '{$new_db_name}' 데이터베이스 복원에 성공했습니다.";

} catch (Exception $e) {
    // If something went wrong, drop the partially created database
    if (!empty($new_db_name)) {
        mysqli_query($link, "DROP DATABASE IF EXISTS `{$new_db_name}`");
    }
    $response['message'] = "복원 실패: " . $e->getMessage();
    error_log("SQL Restore Failed: " . $e->getMessage());
}

echo json_encode($response);
exit;
?>