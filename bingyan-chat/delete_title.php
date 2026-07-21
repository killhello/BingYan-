<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit();
}

if (!isAdmin()) {
    echo json_encode(['success' => false, 'error' => '仅管理员可操作']);
    exit();
}

$targetUser = isset($_POST['username']) ? $_POST['username'] : '';
if (empty($targetUser)) {
    echo json_encode(['success' => false, 'error' => '参数错误']);
    exit();
}

$data = getUserData($targetUser);
if (!$data) {
    echo json_encode(['success' => false, 'error' => '用户不存在']);
    exit();
}

$data['title'] = '';
saveUserData($targetUser, $data);

echo json_encode(['success' => true]);
