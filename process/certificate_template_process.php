<?php
require_once __DIR__ . '/../common.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => '잘못된 요청입니다.'];

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 최고 관리자만 접근 가능
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    $response['message'] = '권한이 없습니다.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save') {
        $template_key = $_POST['template_key'] ?? '';
        $content = $_POST['content'] ?? '';
        if (!empty($template_key)) {
            if (update_certificate_template($template_key, $content)) {
                $response = ['success' => true, 'message' => '템플릿이 저장되었습니다.'];
            } else {
                $response['message'] = '템플릿 저장에 실패했습니다.';
            }
        }
    } elseif ($action === 'initialize_single') {
        $template_key = $_POST['template_key'] ?? '';
        if (!empty($template_key)) {
            $default_file_path = __DIR__ . '/../templates/certificates/defaults/' . $template_key . '.html';
            if (file_exists($default_file_path)) {
                $content = file_get_contents($default_file_path);
                if (update_certificate_template($template_key, $content)) {
                    $response = ['success' => true, 'message' => "'{$template_key}' 템플릿이 초기화되었습니다."];
                } else {
                    $response['message'] = '템플릿 초기화에 실패했습니다.';
                }
            } else {
                $response['message'] = '기본 템플릿 파일을 찾을 수 없습니다.';
            }
        }
    } elseif ($action === 'save_as_default') {
        $template_key = $_POST['template_key'] ?? '';
        $content = $_POST['content'] ?? '';
        if (!empty($template_key)) {
            $default_file_path = __DIR__ . '/../templates/certificates/defaults/' . $template_key . '.html';
            if (file_put_contents($default_file_path, $content) !== false) {
                $response = ['success' => true, 'message' => "'{$template_key}'의 기본값이 저장되었습니다."];
            } else {
                $response['message'] = '기본값 저장에 실패했습니다.';
            }
        } else {
            $response['message'] = '템플릿 키가 없습니다.';
        }
    }
}

echo json_encode($response);
exit;
?>