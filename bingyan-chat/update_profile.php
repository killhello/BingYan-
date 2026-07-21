<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit();
}

$currentUser = $_SESSION['username'];
$usersDir = 'users';
$userFile = $usersDir . '/' . $currentUser . '.json';

if (!file_exists($userFile)) {
    echo json_encode(['success' => false, 'error' => 'user_not_found']);
    exit();
}

$userData = json_decode(file_get_contents($userFile), true);
if (!is_array($userData)) {
    echo json_encode(['success' => false, 'error' => 'user_data_error']);
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'update_nickname') {
    $nickname = isset($_POST['nickname']) ? trim($_POST['nickname']) : '';
    if (empty($nickname)) {
        echo json_encode(['success' => false, 'error' => 'empty_nickname']);
        exit();
    }
    if (mb_strlen($nickname) > 20) {
        echo json_encode(['success' => false, 'error' => 'nickname_too_long']);
        exit();
    }
    $userData['nickname'] = htmlspecialchars($nickname, ENT_QUOTES, 'UTF-8');
    file_put_contents($userFile, json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'upload_avatar') {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => '上传出错: ' . $_FILES['avatar']['error']]);
        exit();
    }

    $file = $_FILES['avatar'];

    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        echo json_encode(['success' => false, 'error' => '无效的图片文件']);
        exit();
    }
    $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!in_array($imageInfo[2], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => '仅支持 JPG/PNG/GIF/WebP']);
        exit();
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => '文件超过2MB']);
        exit();
    }

    $saveDir = __DIR__ . '/photo';
    if (!is_dir($saveDir)) { mkdir($saveDir, 0755, true); }

    $savePath = $saveDir . '/' . $currentUser . '.png';
    if (file_exists($savePath)) { unlink($savePath); }

    $saved = false;
    if (function_exists('imagecreatefromstring')) {
        $src = imagecreatefromstring(file_get_contents($file['tmp_name']));
        if ($src) {
            $sw = imagesx($src); $sh = imagesy($src); $size = min($sw, $sh);
            $dst = imagecreatetruecolor(200, 200);
            imagecopyresampled($dst, $src, 0, 0, ($sw - $size) / 2, ($sh - $size) / 2, 200, 200, $size, $size);
            $saved = imagepng($dst, $savePath);
            imagedestroy($src); imagedestroy($dst);
        }
    }
    if (!$saved) {
        $saved = move_uploaded_file($file['tmp_name'], $savePath);
    }
    if (!$saved) {
        echo json_encode(['success' => false, 'error' => '文件保存失败']);
        exit();
    }

    $userData['avatar'] = 'avatar.php?name=' . $currentUser;
    file_put_contents($userFile, json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'invalid_action']);
