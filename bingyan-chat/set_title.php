<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit();
}

$currentUser = $_SESSION['username'];
$isAdminUser = isAdmin();

$title = isset($_POST['title']) ? trim($_POST['title']) : '';

// 验证标题长度
if (mb_strlen($title) > 20) {
    echo json_encode(['success' => false, 'error' => '头衔不能超过20个字符']);
    exit();
}

$data = getUserData($currentUser);
if (!$data) {
    echo json_encode(['success' => false, 'error' => '用户数据不存在']);
    exit();
}

$today = date('Y-m-d');

// 管理员无限制
if (!$isAdminUser) {
    if ($data['title_date'] !== $today) {
        $data['title_date'] = $today;
        $data['title_count'] = 0;
    }
    if ($data['title_count'] >= 3) {
        echo json_encode(['success' => false, 'error' => '每天只能修改3次头衔']);
        exit();
    }
    $data['title_count']++;
}

$data['title'] = $title;
saveUserData($currentUser, $data);

echo json_encode(['success' => true]);
