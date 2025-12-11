<?php
require_once __DIR__ . '/../common.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 최고 관리자만 접근 가능
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    $_SESSION['error_message'] = '접근 권한이 없습니다.';
    header("location: ../pages/intranet.php");
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'update_system_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $slack_enabled = $_POST['slack_notifications_enabled'] ?? '0';
    $wideshot_api_key = trim($_POST['wideshot_api_key'] ?? '');
    $default_sender_phone = trim($_POST['default_sender_phone'] ?? '');

    // Company Info Fields
    $company_info_fields = [
        'biz_reg_number' => trim($_POST['biz_reg_number'] ?? ''),
        'loan_biz_reg_number' => trim($_POST['loan_biz_reg_number'] ?? ''),
        'company_phone' => trim($_POST['company_phone'] ?? ''),
        'company_fax' => trim($_POST['company_fax'] ?? ''),
        'company_email' => trim($_POST['company_email'] ?? ''),
        'interest_account' => trim($_POST['interest_account'] ?? ''),
        'expense_account' => trim($_POST['expense_account'] ?? '')
    ];

    // Save Slack & SMS Settings
    $settings = [
        'slack_notifications_enabled' => $slack_enabled,
        'wideshot_api_key' => $wideshot_api_key,
        'default_sender_phone' => $default_sender_phone
    ];
    
    // Merge all settings
    $all_settings = array_merge($settings, $company_info_fields);

    foreach ($all_settings as $key => $value) {
        $sql = "INSERT INTO company_info (info_key, info_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE info_value = VALUES(info_value)";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $key, $value);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $_SESSION['message'] = '설정이 저장되었습니다.';
    header("Location: ../pages/settings.php");
    exit;
}

// --- Frequent Memo Management ---
if (isset($_POST['add_frequent_memo']) && !empty($_POST['new_frequent_memo'])) {
    $memo_text = trim($_POST['new_frequent_memo']);
    $stmt = mysqli_prepare($link, "INSERT INTO frequent_memos (memo_text) VALUES (?)");
    mysqli_stmt_bind_param($stmt, "s", $memo_text);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "상용구가 추가되었습니다.";
    } else {
        $_SESSION['error_message'] = "상용구 추가 실패: " . mysqli_error($link);
    }
    mysqli_stmt_close($stmt);
    header("Location: ../pages/settings.php");
    exit;
}

if (isset($_POST['delete_frequent_memo'])) {
    $id = (int)$_POST['delete_frequent_memo'];
    $stmt = mysqli_prepare($link, "DELETE FROM frequent_memos WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "상용구가 삭제되었습니다.";
    } else {
        $_SESSION['error_message'] = "상용구 삭제 실패: " . mysqli_error($link);
    }
    mysqli_stmt_close($stmt);
    header("Location: ../pages/settings.php");
    exit;
}

// --- Classification Code Management ---
if (isset($_POST['add_classification_code']) && !empty($_POST['new_classification_code']) && !empty($_POST['new_classification_name'])) {
    $code = trim($_POST['new_classification_code']);
    $name = trim($_POST['new_classification_name']);
    
    $stmt = mysqli_prepare($link, "INSERT INTO classification_codes (code, name) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ss", $code, $name);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "구분코드가 추가되었습니다.";
    } else {
        // Check for duplicate entry error
        if (mysqli_errno($link) == 1062) {
             $_SESSION['error_message'] = "이미 존재하는 코드입니다.";
        } else {
             $_SESSION['error_message'] = "구분코드 추가 실패: " . mysqli_error($link);
        }
    }
    mysqli_stmt_close($stmt);
    header("Location: ../pages/settings.php");
    exit;
}

if (isset($_POST['delete_classification_code'])) {
    $id = (int)$_POST['delete_classification_code'];
    
    // Check if used in any contract
    $check_stmt = mysqli_prepare($link, "SELECT COUNT(*) as count FROM contract_classifications WHERE classification_code_id = ?");
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($check_stmt);

    if ($row['count'] > 0) {
        $_SESSION['error_message'] = "이 구분코드는 " . $row['count'] . "개의 계약에 사용 중이므로 삭제할 수 없습니다.";
    } else {
        $stmt = mysqli_prepare($link, "DELETE FROM classification_codes WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "구분코드가 삭제되었습니다.";
        } else {
            $_SESSION['error_message'] = "구분코드 삭제 실패: " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt);
    }
    header("Location: ../pages/settings.php");
    exit;
}
?>