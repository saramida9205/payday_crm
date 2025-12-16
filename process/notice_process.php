<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_notices':
            getNotices($link);
            break;
        case 'get_notice':
            getNotice($link);
            break;
        case 'create_notice':
            createNotice($link);
            break;
        case 'update_notice':
            updateNotice($link);
            break;
        case 'delete_notice':
            deleteNotice($link);
            break;
        default:
            throw new Exception("Invalid action: $action");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get list of notices
 */
function getNotices($link)
{
    // Determine limit
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

    $sql = "SELECT n.*, u.name as author_name 
            FROM notices n 
            LEFT JOIN employees u ON n.author_id = u.id 
            ORDER BY n.is_important DESC, n.created_at DESC 
            LIMIT ?";

    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notices = mysqli_fetch_all($result, MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'notices' => $notices]);
}

/**
 * Get a single notice details and increment view count
 */
function getNotice($link)
{
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) throw new Exception("Invalid ID");

    // Increment view count
    $update_sql = "UPDATE notices SET view_count = view_count + 1 WHERE id = ?";
    $update_stmt = mysqli_prepare($link, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $id);
    mysqli_stmt_execute($update_stmt);

    // Fetch details
    $sql = "SELECT n.*, u.name as author_name 
            FROM notices n 
            LEFT JOIN employees u ON n.author_id = u.id 
            WHERE n.id = ?";

    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notice = mysqli_fetch_assoc($result);

    if (!$notice) throw new Exception("Notice not found");

    // Check if current user can edit (Author or Admin)
    $can_edit = ($_SESSION['permission_level'] == '0' || $_SESSION['permission_level'] == 'admin' || $_SESSION['id'] == $notice['author_id']);
    $notice['can_edit'] = $can_edit;

    echo json_encode(['success' => true, 'notice' => $notice]);
}

/**
 * Create a new notice
 */
/**
 * Create a new notice
 */
function createNotice($link)
{
    // Permission check
    if ($_SESSION['permission_level'] != 'admin' && $_SESSION['permission_level'] != '0') {
        throw new Exception("권한이 없습니다.");
    }

    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $is_important = (isset($_POST['is_important']) && $_POST['is_important'] == 1) ? 1 : 0;
    $author_id = $_SESSION['id'];

    if (empty($title) || empty($content)) {
        throw new Exception("제목과 내용을 입력해주세요.");
    }

    // File Upload Handling
    $file_path = null;
    $file_name = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $upload_dir = '../uploads/notices/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $original_filename = $_FILES['attachment']['name'];
        $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $new_filename = uniqid('notice_', true) . '.' . $extension;
        $target_file = $upload_dir . $new_filename;

        // Allow certain file formats
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'hwp'];
        if (!in_array(strtolower($extension), $allowed_types)) {
            throw new Exception("허용되지 않는 파일 형식입니다.");
        }

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
            $file_path = '/uploads/notices/' . $new_filename; // Web accessible path
            $file_name = $original_filename;
        } else {
            throw new Exception("파일 업로드에 실패했습니다.");
        }
    }

    $sql = "INSERT INTO notices (title, content, author_id, is_important, file_path, file_name) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "ssiiss", $title, $content, $author_id, $is_important, $file_path, $file_name);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => '공지사항이 등록되었습니다.']);
    } else {
        throw new Exception("DB Error: " . mysqli_error($link));
    }
}

/**
 * Update an existing notice
 */
function updateNotice($link)
{
    $id = (int)$_POST['id'];
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $is_important = (isset($_POST['is_important']) && $_POST['is_important'] == 1) ? 1 : 0;

    // Permission check (Author or Superadmin)
    $check_sql = "SELECT author_id, file_path FROM notices WHERE id = ?";
    $check_stmt = mysqli_prepare($link, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $notice = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

    if (!$notice) throw new Exception("Notice not found");

    if ($_SESSION['permission_level'] != '0' && $_SESSION['id'] != $notice['author_id']) {
        throw new Exception("수정 권한이 없습니다.");
    }

    // File Upload Handling
    $file_path = $notice['file_path']; // Keep existing file by default
    $file_name = null; // Don't update name unless new file

    // Check if delete file requested
    if (isset($_POST['delete_file']) && $_POST['delete_file'] == '1') {
        // In a real scenario, you might delete the physical file here too
        $file_path = null;
        $file_name = null;
    }

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $upload_dir = '../uploads/notices/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $original_filename = $_FILES['attachment']['name'];
        $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $new_filename = uniqid('notice_', true) . '.' . $extension;
        $target_file = $upload_dir . $new_filename;

        // Allow certain file formats
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'hwp'];
        if (!in_array(strtolower($extension), $allowed_types)) {
            throw new Exception("허용되지 않는 파일 형식입니다.");
        }

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
            $file_path = '/uploads/notices/' . $new_filename;
            $file_name = $original_filename;
        } else {
            throw new Exception("파일 업로드에 실패했습니다.");
        }
    }

    // Build query dynamically based on whether file was updated/deleted
    if (isset($file_name) || (isset($_POST['delete_file']) && $_POST['delete_file'] == '1')) {
        // File changed or deleted
        $sql = "UPDATE notices SET title = ?, content = ?, is_important = ?, file_path = ?, file_name = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        // If file_path is null (deleted), file_name needs to be null effectively or updated. 
        // But logic above: if delete_file, both null. If upload, both set.
        // If maintaining old file, file_name is null in PHP but we shouldn't overwrite it to null in DB if keeping.
        // Wait, simplification: If keeping existing file, we don't want to update these columns ideally, OR we need existing filename.
        // Let's refetch existing filename if we are keeping existing file path and not uploading new one.

        if ($file_path !== null && $file_name === null) {
            // Keeping existing file, but why are we here? Ah, maybe logic above is slightly flawed for 'keeping'.
            // Logic:
            // 1. Delete requested? Set null.
            // 2. New file? Set new values.
            // 3. Neither? Do not touch columns.
        }
    }

    // Revised Update Logic
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        // New file uploaded
        $sql = "UPDATE notices SET title = ?, content = ?, is_important = ?, file_path = ?, file_name = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ssissi", $title, $content, $is_important, $file_path, $file_name, $id);
    } elseif (isset($_POST['delete_file']) && $_POST['delete_file'] == '1') {
        // File deleted
        $empty_path = null;
        $empty_name = null;
        $sql = "UPDATE notices SET title = ?, content = ?, is_important = ?, file_path = ?, file_name = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ssissi", $title, $content, $is_important, $empty_path, $empty_name, $id);
    } else {
        // No file change
        $sql = "UPDATE notices SET title = ?, content = ?, is_important = ? WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "ssii", $title, $content, $is_important, $id);
    }

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => '공지사항이 수정되었습니다.']);
    } else {
        throw new Exception("DB Error: " . mysqli_error($link));
    }
}

/**
 * Delete a notice
 */
function deleteNotice($link)
{
    $id = (int)$_POST['id'];

    // Permission check
    $check_sql = "SELECT author_id FROM notices WHERE id = ?";
    $check_stmt = mysqli_prepare($link, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $notice = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

    if (!$notice) throw new Exception("Notice not found");

    if ($_SESSION['permission_level'] != '0' && $_SESSION['id'] != $notice['author_id']) {
        throw new Exception("삭제 권한이 없습니다.");
    }

    $sql = "DELETE FROM notices WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => '공지사항이 삭제되었습니다.']);
    } else {
        throw new Exception("DB Error: " . mysqli_error($link));
    }
}
