<?php
require_once __DIR__ . '/../common.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check for admin permission
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    $response['message'] = '백업 권한이 없습니다.';
    echo json_encode($response);
    exit;
}

// Initialize progress in session
$_SESSION['backup_progress'] = [
    'in_progress' => true,
    'progress' => 0,
    'message' => '백업 초기화 중...'
];

global $link;

// --- Configuration ---
$source_db = DB_NAME;
$target_db = $source_db . '_' . date('Ymd_His');

set_time_limit(0); // Prevent script timeout during backup

try {
    // 1. Check if backup database already exists
    $check_db_sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?";
    if ($stmt_check = mysqli_prepare($link, $check_db_sql)) {
        mysqli_stmt_bind_param($stmt_check, "s", $target_db);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        if (mysqli_num_rows($result_check) > 0) {
            throw new Exception("백업 데이터베이스('{$target_db}')가 이미 존재합니다. 잠시 후 다시 시도해주세요.");
        }
    }
    $_SESSION['backup_progress']['message'] = "백업 데이터베이스 '{$target_db}' 생성 중...";
    mysqli_stmt_close($stmt_check);

    // 2. Create the new backup database
    if (!mysqli_query($link, "CREATE DATABASE `{$target_db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        throw new Exception("백업 데이터베이스 생성에 실패했습니다: " . mysqli_error($link));
    }

    $_SESSION['backup_progress']['message'] = '테이블 목록 가져오는 중...';
    // 3. Get all tables from the source database
    $tables_result = mysqli_query($link, "SHOW TABLES FROM `{$source_db}`");
    if (!$tables_result) {
        throw new Exception("소스 데이터베이스의 테이블 목록을 가져오는 데 실패했습니다: " . mysqli_error($link));
    }

    $tables = [];
    while ($row = mysqli_fetch_row($tables_result)) {
        $tables[] = $row[0];
    }

    if (empty($tables)) {
        throw new Exception("소스 데이터베이스에 테이블이 없습니다.");
    }

    $total_tables = count($tables);
    $processed_tables = 0;

    // 4. Loop through tables to copy structure and data
    foreach ($tables as $table) {
        $processed_tables++;
        
        // Copy table structure
        $create_table_sql = "CREATE TABLE `{$target_db}`.`{$table}` LIKE `{$source_db}`.`{$table}`";
        if (!mysqli_query($link, $create_table_sql)) {
            throw new Exception("테이블 '{$table}'의 구조 복사에 실패했습니다: " . mysqli_error($link));
        }

        // Copy table data
        $insert_data_sql = "INSERT INTO `{$target_db}`.`{$table}` SELECT * FROM `{$source_db}`.`{$table}`";
        if (!mysqli_query($link, $insert_data_sql)) {
            throw new Exception("테이블 '{$table}'의 데이터 복사에 실패했습니다: " . mysqli_error($link));
        }

        // Update progress
        $_SESSION['backup_progress']['progress'] = round(($processed_tables / $total_tables) * 100);
        $_SESSION['backup_progress']['message'] = "테이블 복사 중: {$table} ({$processed_tables}/{$total_tables})";
        session_write_close(); // Save session data and release lock
        session_start(); // Re-start session for the next loop
    }

    $response['success'] = true;
    $response['message'] = "데이터베이스 백업이 성공적으로 완료되었습니다. (백업명: {$target_db})";

} catch (Exception $e) {
    // If something went wrong, drop the partially created backup database
    mysqli_query($link, "DROP DATABASE IF EXISTS `{$target_db}`");
    $response['message'] = "백업 실패: " . $e->getMessage();
    error_log("DB Backup Failed: " . $e->getMessage());

} finally {
    // Finalize progress
    $_SESSION['backup_progress'] = [
        'in_progress' => false,
        'progress' => 0,
        'message' => ''
    ];
    echo json_encode($response);
    exit;
}

?>