<?php
session_start();
require_once __DIR__ . '/geetest_lib.php';

header('Content-Type: application/json');

// 处理验证码刷新（兼容旧逻辑）
if (isset($_GET['action']) && $_GET['action'] === 'refresh_captcha') {
    echo json_encode(['status' => 'deprecated']);
    exit();
}

$username = trim($_POST['username']);
$password = $_POST['password'];

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'error' => '用户名和密码不能为空']);
    exit();
}

// GeeTest 二次验证
$challenge = isset($_POST['geetest_challenge']) ? $_POST['geetest_challenge'] : '';
$validate  = isset($_POST['geetest_validate'])  ? $_POST['geetest_validate']  : '';
$seccode   = isset($_POST['geetest_seccode'])   ? $_POST['geetest_seccode']   : '';

$config = require __DIR__ . '/geetest_config.php';
$lib = new GeeTestLib($config['id'], $config['key']);

$gtResult = $lib->validate($challenge, $validate, $seccode);
if ($gtResult !== true) {
    $errorMsg = is_string($gtResult) ? $gtResult : '人机验证失败，请重新验证';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit();
}

$usersDir = 'users';
if (!is_dir($usersDir)) {
    mkdir($usersDir, 0755, true);
}
$userFile = $usersDir . '/' . $username . '.json';

if (!file_exists($userFile)) {
    echo json_encode(['success' => false, 'error' => '用户名或密码错误']);
    exit();
}

$userData = json_decode(file_get_contents($userFile), true);

if (password_verify($password, $userData['password'])) {
    $_SESSION['username'] = $username;
    echo json_encode(['success' => true, 'redirect' => 'chat.php']);
    exit();
} else {
    echo json_encode(['success' => false, 'error' => '用户名或密码错误']);
    exit();
}
