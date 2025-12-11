<?php
require_once __DIR__ . '/../common.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => '잘못된 요청입니다.'];

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_backup_progress') {
    $progress_data = $_SESSION['backup_progress'] ?? ['in_progress' => false];
    echo json_encode($progress_data);
    exit;
}


// 최고 관리자만 접근 가능
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    $response['message'] = '권한이 없습니다.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_backup') {
    if (empty($_POST['db_name'])) {
        $response['message'] = '삭제할 데이터베이스 이름이 없습니다.';
    } else {
        global $link;
        $db_to_delete = mysqli_real_escape_string($link, $_POST['db_name']);

        // To prevent accidental deletion of the main DB, check if it contains the backup suffix pattern
        if (strpos($db_to_delete, DB_NAME . '_') !== 0) {
            $response['message'] = '잘못된 백업 데이터베이스 이름 형식입니다. 삭제할 수 없습니다.';
        } else {
            $sql = "DROP DATABASE `{$db_to_delete}`";
            if (mysqli_query($link, $sql)) {
                $response['success'] = true;
                $response['message'] = "백업 데이터베이스 '{$db_to_delete}'가 성공적으로 삭제되었습니다.";
            } else {
                $response['message'] = "삭제 실패: " . mysqli_error($link);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_backup') {
    $source_db_to_restore = $_POST['db_name'] ?? '';
    $target_db = DB_NAME;
    $renamed_prod_db = DB_NAME . '_before_restore_' . date('Ymd_His');

    if (empty($source_db_to_restore)) {
        $response['message'] = '복원할 소스 데이터베이스 이름이 없습니다.';
        echo json_encode($response);
        exit;
    }

    // Initialize progress
    $_SESSION['backup_progress'] = ['in_progress' => true, 'progress' => 0, 'message' => '복원 프로세스 시작...'];
    session_write_close();

    global $link;
    set_time_limit(0);

    try {
        // Step 1: Rename current production DB as a safety backup
        session_start();
        $_SESSION['backup_progress'] = ['in_progress' => true, 'progress' => 5, 'message' => "안전 백업 DB '{$renamed_prod_db}' 생성 중..."];
        session_write_close();

        // Create the safety backup DB
        if (!mysqli_query($link, "CREATE DATABASE `{$renamed_prod_db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
            throw new Exception("안전 백업 DB 생성 실패: " . mysqli_error($link));
        }

        // Get tables from current production DB
        $prod_tables_result = mysqli_query($link, "SHOW TABLES FROM `{$target_db}`");
        if (!$prod_tables_result) throw new Exception("운영 DB 테이블 목록 조회 실패: " . mysqli_error($link));
        $prod_tables = [];
        while ($row = mysqli_fetch_row($prod_tables_result)) $prod_tables[] = $row[0];

        // Copy production data to safety backup DB
        foreach ($prod_tables as $table) {
            session_start();
            $_SESSION['backup_progress']['message'] = "운영 데이터 백업 중: {$table}";
            session_write_close();
            mysqli_query($link, "CREATE TABLE `{$renamed_prod_db}`.`{$table}` LIKE `{$target_db}`.`{$table}`");
            mysqli_query($link, "INSERT INTO `{$renamed_prod_db}`.`{$table}` SELECT * FROM `{$target_db}`.`{$table}`");
        }

        // Step 2: Drop all tables from the current production DB
        session_start();
        $_SESSION['backup_progress'] = ['in_progress' => true, 'progress' => 25, 'message' => "기존 운영 DB 초기화 중..."];
        session_write_close();
        mysqli_query($link, "SET FOREIGN_KEY_CHECKS = 0");
        foreach ($prod_tables as $table) {
            mysqli_query($link, "DROP TABLE `{$target_db}`.`{$table}`");
        }
        mysqli_query($link, "SET FOREIGN_KEY_CHECKS = 1");

        /* This part is no longer needed as we are not renaming and recreating
        session_write_close();
        if (!mysqli_query($link, "CREATE DATABASE `{$target_db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
            // Try to revert the rename if creation fails
            mysqli_query($link, "RENAME DATABASE `{$renamed_prod_db}` TO `{$target_db}`");
            throw new Exception("새 운영 DB 생성에 실패했습니다: " . mysqli_error($link));
        }

        */
        // Step 3: Get all tables from the source backup DB
        session_start();
        $_SESSION['backup_progress'] = ['in_progress' => true, 'progress' => 40, 'message' => '백업 DB로부터 테이블 목록 가져오는 중...'];
        session_write_close();
        $tables_result = mysqli_query($link, "SHOW TABLES FROM `{$source_db_to_restore}`");
        if (!$tables_result) throw new Exception("백업 DB 테이블 목록 조회 실패: " . mysqli_error($link));
        
        $tables = [];
        while ($row = mysqli_fetch_row($tables_result)) $tables[] = $row[0];

        // Step 4: Copy tables and data
        $total_tables = count($tables);
        $processed_tables = 0;
        foreach ($tables as $table) {
            session_start();
            $progress = 40 + round(($processed_tables / $total_tables) * 55);
            $_SESSION['backup_progress'] = ['in_progress' => true, 'progress' => $progress, 'message' => "테이블 복사 중: {$table} ({$processed_tables}/{$total_tables})"];
            session_write_close();

            // Copy structure
            $create_sql = "CREATE TABLE `{$target_db}`.`{$table}` LIKE `{$source_db_to_restore}`.`{$table}`";
            if (!mysqli_query($link, $create_sql)) throw new Exception("테이블 '{$table}' 구조 복사 실패: " . mysqli_error($link));

            // Copy data
            $insert_sql = "INSERT INTO `{$target_db}`.`{$table}` SELECT * FROM `{$source_db_to_restore}`.`{$table}`";
            if (!mysqli_query($link, $insert_sql)) throw new Exception("테이블 '{$table}' 데이터 복사 실패: " . mysqli_error($link));
            
            $processed_tables++;
        }

        $response['success'] = true;
        $response['message'] = "데이터베이스 복원이 성공적으로 완료되었습니다.";

    } catch (Exception $e) {
        $response['message'] = "복원 실패: " . $e->getMessage();
        error_log("DB Restore Failed: " . $e->getMessage());
    } finally {
        session_start();
        $_SESSION['backup_progress'] = ['in_progress' => false, 'progress' => 0, 'message' => ''];
        session_write_close();
    }

    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_old_backups') {
    $db_names_to_delete = $_POST['db_names'] ?? [];
    if (empty($db_names_to_delete)) {
        $response['message'] = '삭제할 데이터베이스 목록이 없습니다.';
        echo json_encode($response);
        exit;
    }

    global $link;
    $deleted_count = 0;
    $errors = [];

    foreach ($db_names_to_delete as $db_name) {
        $db_to_delete = mysqli_real_escape_string($link, $db_name);

        if (strpos($db_to_delete, DB_NAME . '_') !== 0) {
            $errors[] = "{$db_to_delete}: 잘못된 형식의 DB 이름";
            continue;
        }

        $sql = "DROP DATABASE `{$db_to_delete}`";
        if (mysqli_query($link, $sql)) {
            $deleted_count++;
        } else {
            $errors[] = "{$db_to_delete}: " . mysqli_error($link);
        }
    }

    $response['success'] = empty($errors);
    $response['message'] = "총 {$deleted_count}개의 오래된 백업을 삭제했습니다.";
    if (!empty($errors)) {
        $response['message'] .= "\n일부 삭제 실패:\n" . implode("\n", $errors);
    }
    echo json_encode($response);
    exit;
}

echo json_encode($response);
exit;
?>