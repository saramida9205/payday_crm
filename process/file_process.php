<?php
require_once __DIR__ . '/../common.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$current_user_id = $_SESSION['id'];

// 허용할 파일 확장자 목록 (보안 강화)
$allowed_extensions = [
    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tif', 'tiff',
    'pdf',
    'xls', 'xlsx',
    'ppt', 'pptx',
    'doc', 'docx',
    'hwp'
];

if ($action === 'upload_file' && $_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');

    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $memo = isset($_POST['memo']) ? trim($_POST['memo']) : '';

    if ($customer_id <= 0) {
        echo json_encode(['success' => false, 'message' => '유효하지 않은 고객 ID입니다.']);
        exit;
    }
    // 여러 파일 업로드(customer_file[])로 인해 배열로 수신되므로 첫 번째 요소를 확인합니다.
    if (!isset($_FILES['customer_file']['name'][0]) || $_FILES['customer_file']['error'][0] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => '파일 업로드 중 오류가 발생했습니다.']);
        exit;
    }

    $original_filename = basename($_FILES['customer_file']['name'][0]);
    $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

    // 파일 확장자 검사
    if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => '허용되지 않는 파일 형식입니다.']);
        exit;
    }

    // 파일 저장 경로 설정
    $upload_dir = __DIR__ . '/../uploads/customer_files/' . $customer_id . '/';
    if (!is_dir($upload_dir)) {
        // mkdir의 세 번째 파라미터(recursive)를 true로 설정하면 상위 디렉토리도 함께 생성됩니다.
        // 권한은 0755가 일반적입니다.
        mkdir($upload_dir, 0755, true);
    }

    // 고유한 파일명 생성
    $stored_filename = uniqid(date('YmdHis_'), true) . '.' . $file_extension;
    $file_path = $upload_dir . $stored_filename;

    if (move_uploaded_file($_FILES['customer_file']['tmp_name'][0], $file_path)) {
        // DB에 파일 정보 저장
        $sql = "INSERT INTO customer_files (customer_id, uploader_id, original_filename, stored_filename, file_path, file_type, file_size, memo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            $db_path = 'uploads/customer_files/' . $customer_id . '/' . $stored_filename; // 상대 경로로 저장
            $file_type = $_FILES['customer_file']['type'][0];
            $file_size = $_FILES['customer_file']['size'][0];
            mysqli_stmt_bind_param($stmt, "iissssis", $customer_id, $current_user_id, $original_filename, $stored_filename, $db_path, $file_type, $file_size, $memo);
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => '파일이 성공적으로 업로드되었습니다.']);
            } else {
                unlink($file_path); // DB 저장 실패 시 업로드된 파일 삭제
                echo json_encode(['success' => false, 'message' => '데이터베이스 저장에 실패했습니다.']);
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '파일을 서버에 저장하지 못했습니다.']);
    }
    exit;
}

if ($action === 'delete_file' && $_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $file_id = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;

    if ($file_id <= 0) {
        echo json_encode(['success' => false, 'message' => '유효하지 않은 파일 ID입니다.']);
        exit;
    }

    // 파일 정보 가져오기
    $stmt = mysqli_prepare($link, "SELECT file_path FROM customer_files WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $file_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $file_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($file_data) {
        // DB에서 삭제
        $stmt_delete = mysqli_prepare($link, "DELETE FROM customer_files WHERE id = ?");
        mysqli_stmt_bind_param($stmt_delete, "i", $file_id);
        if (mysqli_stmt_execute($stmt_delete)) {
            // 서버에서 파일 삭제
            $full_path = __DIR__ . '/../' . $file_data['file_path'];
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            echo json_encode(['success' => true, 'message' => '파일이 삭제되었습니다.']);
        } else {
            echo json_encode(['success' => false, 'message' => '데이터베이스에서 파일 정보를 삭제하지 못했습니다.']);
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        echo json_encode(['success' => false, 'message' => '파일을 찾을 수 없습니다.']);
    }
    exit;
}

if ($action === 'update_file_memo' && $_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $file_id = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    $memo = isset($_POST['memo']) ? trim($_POST['memo']) : '';

    if ($file_id <= 0) {
        echo json_encode(['success' => false, 'message' => '유효하지 않은 파일 ID입니다.']);
        exit;
    }

    $sql = "UPDATE customer_files SET memo = ? WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $memo, $file_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => '메모가 성공적으로 업데이트되었습니다.']);
        } else {
            echo json_encode(['success' => false, 'message' => '데이터베이스 업데이트에 실패했습니다.']);
        }
        mysqli_stmt_close($stmt);
    }
    exit;
}

if ($action === 'get_file_content') {
    header('Content-Type: application/json');
    if (!isset($_GET['file_id'])) {
        echo json_encode(['success' => false, 'message' => '파일 ID가 없습니다.']);
        exit;
    }

    $file_id = (int)$_GET['file_id'];
    
    $stmt = mysqli_prepare($link, "SELECT file_path FROM customer_files WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $file_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $file_info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$file_info) {
        echo json_encode(['success' => false, 'message' => '파일을 찾을 수 없습니다.']);
        exit;
    }

    // 보안 검사: 현재 로그인한 사용자가 이 파일에 접근할 권한이 있는지 확인하는 로직을 여기에 추가하는 것이 좋습니다.
    // 예: if (!user_has_permission_for_file($user_id, $file_id)) { ... }

    $file_path = __DIR__ . '/../' . $file_info['file_path'];

    if (file_exists($file_path)) {
        $file_content = file_get_contents($file_path);
        $base64_content = base64_encode($file_content);
        
        echo json_encode(['success' => true, 'content' => $base64_content]);
    } else {
        echo json_encode(['success' => false, 'message' => '서버에서 파일을 찾을 수 없습니다.']);
    }
    exit;
}

if ($action === 'download_file' && isset($_GET['file_id'])) {
    $file_id = (int)$_GET['file_id'];

    $stmt = mysqli_prepare($link, "SELECT * FROM customer_files WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $file_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $file = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($file) {
        $filepath = __DIR__ . '/../' . $file['file_path'];
        if (file_exists($filepath)) {
            header('Content-Type: ' . $file['file_type']);
            
            // inline 파라미터가 있으면 브라우저에서 바로 열도록 설정 (PDF 미리보기용)
            $disposition = isset($_GET['inline']) ? 'inline' : 'attachment';
            header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($file['original_filename']) . '"');
            
            header('Content-Length: ' . filesize($filepath));
            // 불필요하거나 중복될 수 있는 헤더 정리
            // header('Content-Description: File Transfer');
            // header('Expires: 0');
            // header('Cache-Control: must-revalidate');
            // header('Pragma: public');
            flush(); // Flush system output buffer
            readfile($filepath);
            exit;
        }
    }
    http_response_code(404);
    echo "파일을 찾을 수 없습니다.";
    exit;
}

function getCustomerFiles($link, $customer_id) {
    $sql = "SELECT f.*, e.name as uploader_name 
            FROM customer_files f
            JOIN employees e ON f.uploader_id = e.id
            WHERE f.customer_id = ? 
            ORDER BY f.uploaded_at DESC";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $customer_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $files = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $files;
    }
    return [];
}
?>
