<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username']) || !isAdmin()) {
    echo json_encode(['success' => false, 'error' => '无权限']);
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'list') {
    $users = [];
    $files = glob('users/*.json');
    sort($files);
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) continue;
        $users[] = [
            'username' => $data['username'] ?? basename($file, '.json'),
            'nickname' => $data['nickname'] ?? $data['username'] ?? '',
            'by_id'    => $data['by_id'] ?? '',
            'banned'   => !empty($data['banned']),
            'muted'    => !empty($data['muted']),
            'message_count' => $data['message_count'] ?? 0,
            'created_at'    => $data['created_at'] ?? '',
        ];
    }
    echo json_encode(['success' => true, 'users' => $users]);
    exit();
}

$targetUser = isset($_POST['username']) ? trim($_POST['username']) : '';
if (empty($targetUser)) {
    echo json_encode(['success' => false, 'error' => '参数错误']);
    exit();
}

$data = getUserData($targetUser);
if (!$data) {
    echo json_encode(['success' => false, 'error' => '用户不存在']);
    exit();
}

switch ($action) {
    case 'ban':
        $data['banned'] = true;
        saveUserData($targetUser, $data);
        echo json_encode(['success' => true, 'message' => '已封禁']);
        break;
    case 'unban':
        $data['banned'] = false;
        saveUserData($targetUser, $data);
        echo json_encode(['success' => true, 'message' => '已解封']);
        break;
    case 'mute':
        $data['muted'] = true;
        saveUserData($targetUser, $data);
        echo json_encode(['success' => true, 'message' => '已禁言']);
        break;
    case 'unmute':
        $data['muted'] = false;
        saveUserData($targetUser, $data);
        echo json_encode(['success' => true, 'message' => '已解除禁言']);
        break;
    case 'delete':
        $file = 'users/' . $targetUser . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
        echo json_encode(['success' => true, 'message' => '已注销']);
        break;
    default:
        echo json_encode(['success' => false, 'error' => '未知操作']);
}
