<?php
require_once __DIR__ . '/../common.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 최고 관리자만 접근 가능
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    $response['message'] = '권한이 없습니다.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed_keys = ['company_name', 'ceo_name', 'company_address', 'company_phone', 'manager_name', 'manager_phone'];
    $errors = [];

    foreach ($allowed_keys as $key) {
        if (isset($_POST[$key])) {
            if (!update_company_info($link, $key, $_POST[$key])) {
                $errors[] = $key;
            }
        }
    }

    // Handle file upload for company seal
    if (isset($_FILES['company_seal_image']) && $_FILES['company_seal_image']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/company/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // For simplicity, always name the seal image 'seal.png'
        $seal_filename = 'seal.png'; 
        $destination = $upload_dir . $seal_filename;

        // Check file type
        $imageFileType = strtolower(pathinfo($_FILES["company_seal_image"]["name"], PATHINFO_EXTENSION));
        if($imageFileType != "png") {
             $errors[] = "인감 이미지는 PNG 형식만 가능합니다.";
        } else {
            if (!move_uploaded_file($_FILES["company_seal_image"]["tmp_name"], $destination)) {
                $errors[] = "인감 이미지 업로드에 실패했습니다.";
            }
        }
    }


    $response['success'] = empty($errors);
    $response['message'] = empty($errors) ? '정보가 저장되었습니다.' : '일부 정보 저장에 실패했습니다: ' . implode(', ', $errors);
}

echo json_encode($response);
exit;