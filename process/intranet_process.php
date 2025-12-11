<?php
require_once __DIR__ . '/../common.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // For AJAX requests, send an error response instead of redirecting
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
        exit();
    }
    header("location: ../login.php");
    exit;
}

$action = $_REQUEST['action'] ?? '';
$current_user_id = $_SESSION['id'];

if ($action === 'send_message' && $_SERVER["REQUEST_METHOD"] == "POST") {
    $recipient_id = (int)$_POST['recipient_id'];
    $message_text = trim($_POST['message_text']);
    $contract_id = !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null;

    if ($recipient_id > 0 && !empty($message_text)) {
        $sql = "INSERT INTO internal_messages (sender_id, recipient_id, message_text, contract_id) VALUES (?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            // AJAX 요청인지 확인
            $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

            $recipient_name = '';
            if ($is_ajax) {
                $stmt_name = mysqli_prepare($link, "SELECT name FROM employees WHERE id = ?");
                mysqli_stmt_bind_param($stmt_name, "i", $recipient_id);
                mysqli_stmt_execute($stmt_name);
                $recipient_name = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_name))['name'] ?? '';
            }

            mysqli_stmt_bind_param($stmt, "iisi", $current_user_id, $recipient_id, $message_text, $contract_id);
            if (mysqli_stmt_execute($stmt)) {
                $response = ['success' => true, 'message' => '메시지를 성공적으로 보냈습니다.', 'recipient_name' => $recipient_name];
            } else {
                $response = ['success' => false, 'message' => '메시지 전송에 실패했습니다.'];
            }
            mysqli_stmt_close($stmt);

            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => '메시지를 성공적으로 보냈습니다.']);
                exit();
            } else {
                $_SESSION['message'] = $response['message'];
            }
        }
    } else {
        $response = ['success' => false, 'message' => '받는 사람과 메시지 내용을 모두 입력해주세요.'];
    }

    if (!$is_ajax) {
        header("Location: ../pages/intranet.php");
        exit();
    }
    echo json_encode($response);
}

if ($action === 'mark_as_read' && $_SERVER["REQUEST_METHOD"] == "GET") {
    header('Content-Type: application/json');
    $message_id = (int)$_GET['message_id'];
    if ($message_id > 0) {
        $sql = "UPDATE internal_messages SET read_at = NOW() WHERE id = ? AND recipient_id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $message_id, $current_user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'check_new_messages' && $_SERVER["REQUEST_METHOD"] == "GET") {
    header('Content-Type: application/json');
    $messages = getUnreadMessages($link, $current_user_id);
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit();
}

if ($action === 'mark_as_read_bulk' && $_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $message_ids = $input['message_ids'] ?? [];

    if (empty($message_ids)) {
        echo json_encode(['success' => false, 'message' => 'No message IDs provided.']);
        exit();
    }

    // Ensure all IDs are integers for security
    $sanitized_ids = array_map('intval', $message_ids);
    $placeholders = implode(',', array_fill(0, count($sanitized_ids), '?'));
    $types = str_repeat('i', count($sanitized_ids));

    $sql = "UPDATE internal_messages SET read_at = NOW() WHERE id IN ($placeholders) AND recipient_id = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        $params = array_merge($sanitized_ids, [$current_user_id]);
        $types .= 'i';
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark messages as read.']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit();
}

if ($action === 'delete_message' && $_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;

    if ($message_id <= 0) {
        echo json_encode(['success' => false, 'message' => '잘못된 메시지 ID입니다.']);
        exit();
    }

    // 1. 메시지 정보 가져오기 (sender, recipient, delete status)
    $stmt_get = mysqli_prepare($link, "SELECT sender_id, recipient_id, deleted_by_sender, deleted_by_recipient FROM internal_messages WHERE id = ?");
    mysqli_stmt_bind_param($stmt_get, "i", $message_id);
    mysqli_stmt_execute($stmt_get);
    $msg_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
    mysqli_stmt_close($stmt_get);

    if (!$msg_info) {
        echo json_encode(['success' => false, 'message' => '메시지를 찾을 수 없습니다.']);
        exit();
    }

    $is_sender = ($msg_info['sender_id'] == $current_user_id);
    $is_recipient = ($msg_info['recipient_id'] == $current_user_id);

    if (!$is_sender && !$is_recipient) {
        echo json_encode(['success' => false, 'message' => '메시지를 삭제할 권한이 없습니다.']);
        exit();
    }

    // 2. 삭제 로직 실행 - 더 명확하게 수정
    if ($is_sender && $is_recipient) { // 자기 자신에게 보낸 메시지
        // 양쪽 모두에서 삭제 처리
        $sql = "UPDATE internal_messages SET deleted_by_sender = 1, deleted_by_recipient = 1 WHERE id = ?";
    } elseif ($is_sender) {
        // 보낸 사람이 삭제하는 경우
        if ($msg_info['deleted_by_recipient']) {
            // 받는 사람이 이미 삭제했으면, 영구 삭제
            $sql = "DELETE FROM internal_messages WHERE id = ?";
        } else {
            // 아니면, 보낸 사람 쪽에서만 소프트 삭제
            $sql = "UPDATE internal_messages SET deleted_by_sender = 1 WHERE id = ?";
        }
    } else {
        // 받는 사람이 삭제하는 경우
        if ($msg_info['deleted_by_sender']) {
            // 보낸 사람이 이미 삭제했으면, 영구 삭제
            $sql = "DELETE FROM internal_messages WHERE id = ?";
        } else {
            // 아니면, 받는 사람 쪽에서만 소프트 삭제
            $sql = "UPDATE internal_messages SET deleted_by_recipient = 1 WHERE id = ?";
        }
    }

    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $message_id);

    if ($stmt && mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => '메시지가 성공적으로 삭제되었습니다.']);
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => '삭제 처리 중 오류가 발생했습니다.']);
    }
    exit();
}

function getEmployeesForMessaging($link) {
    $sql = "SELECT id, name FROM employees ORDER BY name ASC";
    $result = mysqli_query($link, $sql);
    $employees = mysqli_fetch_all($result, MYSQLI_ASSOC);
    return $employees;
}

/**
 * Fetches all messages (received and sent) for a user in a single query for performance.
 */
function getMessagesForUser($link, $user_id, $limit, $page_recv, $page_sent) {
    $offset_recv = ($page_recv - 1) * $limit;
    $offset_sent = ($page_sent - 1) * $limit;
    
    // Combine count queries into one for better performance
    $sql_count = "SELECT 
                    (SELECT COUNT(*) FROM internal_messages WHERE recipient_id = ? AND deleted_by_recipient = 0) as total_recv,
                    (SELECT COUNT(*) FROM internal_messages WHERE sender_id = ? AND deleted_by_sender = 0) as total_sent";
    
    $stmt_count = mysqli_prepare($link, $sql_count);
    if (!$stmt_count) {
        // Handle error, e.g., log it and return empty data
        error_log("Failed to prepare count statement: " . mysqli_error($link));
        return ['received' => [], 'sent' => [], 'total_recv' => 0, 'total_sent' => 0];
    }
    mysqli_stmt_bind_param($stmt_count, "ii", $user_id, $user_id);    
    mysqli_stmt_execute($stmt_count);
    $counts = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count));
    mysqli_stmt_close($stmt_count);

    $received = getReceivedMessages($link, $user_id, $limit, $offset_recv);
    $sent = getSentMessages($link, $user_id, $limit, $offset_sent);

    return [
        'received' => $received,
        'sent' => $sent,
        'total_recv' => $counts['total_recv'] ?? 0,
        'total_sent' => $counts['total_sent'] ?? 0,
    ];
}


function getReceivedMessages($link, $recipient_id, $limit, $offset) {
    $sql = "SELECT m.*, e.name as sender_name, m.sender_id, m.recipient_id, c.customer_id, m.deleted_by_sender, m.deleted_by_recipient
            FROM internal_messages m 
            JOIN employees e ON m.sender_id = e.id
            LEFT JOIN contracts c ON m.contract_id = c.id
            WHERE m.recipient_id = ? AND m.deleted_by_recipient = 0
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind parameters: recipient_id (i), limit (i), offset (i)
        mysqli_stmt_bind_param($stmt, "iii", $recipient_id, $limit, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $messages;
    }
    return [];
}

function getUnreadMessages($link, $recipient_id) {
    $sql = "SELECT m.*, e.name as sender_name, m.sender_id, m.recipient_id, c.customer_id, m.deleted_by_sender, m.deleted_by_recipient
            FROM internal_messages m 
            JOIN employees e ON m.sender_id = e.id
            LEFT JOIN contracts c ON m.contract_id = c.id
            WHERE m.recipient_id = ? AND m.read_at IS NULL AND m.deleted_by_recipient = 0
            ORDER BY m.created_at ASC"; // Fetch oldest unread first
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $recipient_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $messages;
    }
    return [];
}

function getSentMessages($link, $sender_id, $limit, $offset) {
    $sql = "SELECT m.*, e.name as recipient_name, m.sender_id, m.recipient_id, c.customer_id, m.deleted_by_sender, m.deleted_by_recipient
            FROM internal_messages m 
            JOIN employees e ON m.recipient_id = e.id 
            LEFT JOIN contracts c ON m.contract_id = c.id 
            WHERE m.sender_id = ? AND m.deleted_by_sender = 0
            ORDER BY m.created_at DESC 
            LIMIT ? OFFSET ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iii", $sender_id, $limit, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $messages = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $messages;
    }
    return [];
}

function getTotalReceivedMessages($link, $recipient_id) {
    $sql = "SELECT COUNT(*) FROM internal_messages WHERE recipient_id = ? AND deleted_by_recipient = 0";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $recipient_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_row($result)[0] ?? 0;
    }
    return 0;
}

function getTotalSentMessages($link, $sender_id) {
    $sql = "SELECT COUNT(*) FROM internal_messages WHERE sender_id = ? AND deleted_by_sender = 0";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $sender_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_row($result)[0] ?? 0;
    }
    return 0;
}
?>