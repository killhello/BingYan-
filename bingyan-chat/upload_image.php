<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(array('status' => 'error', 'message' => '未登录'), JSON_UNESCAPED_UNICODE);
    exit();
}

if (empty($_FILES['image'])) {
    echo json_encode(array('status' => 'error', 'message' => '没有选择上传的文件'), JSON_UNESCAPED_UNICODE);
    exit();
}

$imageInfo = getimagesize($_FILES['image']['tmp_name']);
if ($imageInfo === false) {
    echo json_encode(array('status' => 'error', 'message' => '无效的图片文件'), JSON_UNESCAPED_UNICODE);
    exit();
}

$allowedTypes = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP);
if (!in_array($imageInfo[2], $allowedTypes)) {
    echo json_encode(array('status' => 'error', 'message' => '仅支持 JPG/PNG/GIF/WebP 格式图片'), JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_FILES['image']['size'] > 10 * 1024 * 1024) {
    echo json_encode(array('status' => 'error', 'message' => '图片大小不能超过10MB'), JSON_UNESCAPED_UNICODE);
    exit();
}

$extMap = array(IMAGETYPE_JPEG => '.jpg', IMAGETYPE_PNG => '.png', IMAGETYPE_GIF => '.gif', IMAGETYPE_WEBP => '.webp');
$ext = $extMap[$imageInfo[2]];

$saveDir = __DIR__ . '/uploads';
if (!is_dir($saveDir)) {
    mkdir($saveDir, 0755, true);
}

$filename = uniqid('img_', true) . $ext;
$savePath = $saveDir . '/' . $filename;

if (!move_uploaded_file($_FILES['image']['tmp_name'], $savePath)) {
    echo json_encode(array('status' => 'error', 'message' => '文件保存失败'), JSON_UNESCAPED_UNICODE);
    exit();
}

$publicUrl = '/uploads/' . $filename;

$result = array(
    'status' => 'success',
    'data' => array(
        'public_url' => $publicUrl
    ),
    'message' => 'success'
);
echo json_encode($result);
