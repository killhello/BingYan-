<?php
session_start();
require_once __DIR__ . '/geetest_lib.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '非法请求']);
    exit();
}

$username = trim($_POST['username']);
$password = $_POST['password'];
$confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'error' => '用户名和密码不能为空']);
    exit();
}

if (strlen($username) < 3 || strlen($username) > 20) {
    echo json_encode(['success' => false, 'error' => '用户名长度必须在3-20个字符之间']);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => '密码长度不能少于6个字符']);
    exit();
}

if ($password !== $confirmPassword) {
    echo json_encode(['success' => false, 'error' => '两次输入的密码不一致']);
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

if (file_exists($userFile)) {
    echo json_encode(['success' => false, 'error' => '用户名已存在']);
    exit();
}

// 生成BY号（6位大写字母+数字）
function generateBYId() {
    return 'BY' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
}

$byId = generateBYId();

$userData = array(
    'username' => $username,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'by_id' => $byId,
    'created_at' => date('Y-m-d H:i:s')
);

if (file_put_contents($userFile, json_encode($userData))) {
    $_SESSION['username'] = $username;
    echo json_encode(['success' => true, 'redirect' => 'chat.php']);
    exit();
} else {
    echo json_encode(['success' => false, 'error' => '注册失败，请重试']);
    exit();
}
