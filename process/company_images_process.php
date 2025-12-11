<?php
session_start();
require_once '../common.php';

// Check for admin permissions
if (!isset($_SESSION['permission_level']) || $_SESSION['permission_level'] != 0) {
    header("location: ../pages/login.php");
    exit;
}

$upload_dir = '../uploads/company/';

// Ensure upload directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$files_map = [
    'logo' => 'logo.png',
    'regcert' => 'regcert.png',
    'loancert' => 'loancert.png',
    'bank01' => 'bank01.png',
    'bank02' => 'bank02.png'
];

$success_count = 0;
$error_messages = [];

foreach ($files_map as $input_name => $target_filename) {
    if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == UPLOAD_ERR_OK) {
        $tmp_name = $_FILES[$input_name]['tmp_name'];
        $target_path = $upload_dir . $target_filename;

        // Validate image type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($tmp_name);

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

        if (in_array($mime_type, $allowed_types)) {
            if (move_uploaded_file($tmp_name, $target_path)) {
                $success_count++;
            } else {
                $error_messages[] = "$input_name 업로드 실패: 파일 이동 오류";
            }
        } else {
            $error_messages[] = "$input_name 업로드 실패: 허용되지 않는 파일 형식 ($mime_type)";
        }
    }
}

if ($success_count > 0) {
    $_SESSION['message'] = "$success_count 개의 이미지가 성공적으로 업로드되었습니다.";
}

if (!empty($error_messages)) {
    $_SESSION['error_message'] = implode("<br>", $error_messages);
}

if ($success_count == 0 && empty($error_messages)) {
    $_SESSION['message'] = "변경된 사항이 없습니다.";
}

header("location: ../pages/company_images.php");
exit;
