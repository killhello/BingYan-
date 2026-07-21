<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit();
}

require_once 'config.php';

$currentUser = $_SESSION['username'];
$isAdminUser = isAdmin();
$chatDir = 'chatlogs';

// 接收参数
$targetUser = isset($_POST['username']) ? $_POST['username'] : '';
$timestamp = isset($_POST['timestamp']) ? intval($_POST['timestamp']) : 0;
$messageText = isset($_POST['message']) ? $_POST['message'] : '';
$imageUrl = isset($_POST['image_url']) ? $_POST['image_url'] : '';
$groupId = isset($_POST['group_id']) ? $_POST['group_id'] : '';
$privateId = isset($_POST['private_id']) ? $_POST['private_id'] : '';
$action = isset($_POST['action']) ? $_POST['action'] : 'recall';

if (empty($targetUser) || $timestamp === 0) {
    echo json_encode(['success' => false, 'error' => '参数不完整']);
    exit();
}

// 非管理员只能撤回自己的消息
if ($targetUser !== $currentUser && !$isAdminUser) {
    echo json_encode(['success' => false, 'error' => '只能撤回自己的消息']);
    exit();
}

// 时间限制：2分钟内可撤回
$timeDiff = time() - $timestamp;
if ($timeDiff > 120 && !$isAdminUser) {
    echo json_encode(['success' => false, 'error' => '超过2分钟无法撤回']);
    exit();
}

// 确定搜索目录
if (!empty($privateId)) {
    $searchDir = $chatDir . '/private/' . $privateId;
} elseif (!empty($groupId)) {
    $searchDir = $chatDir . '/groups/' . $groupId;
} else {
    $searchDir = $chatDir;
}

if (!is_dir($searchDir)) {
    echo json_encode(['success' => false, 'error' => '目录不存在']);
    exit();
}

// 查找所有分片文件
$chunks = glob($searchDir . '/chatlog_*.json');
if (empty($chunks)) {
    $oldFile = $searchDir . '/chatlog.json';
    if (file_exists($oldFile)) {
        $chunks = [$oldFile];
    }
}

if (empty($chunks)) {
    echo json_encode(['success' => false, 'error' => '没有消息文件']);
    exit();
}

$found = false;
$msgId = md5($targetUser . '|' . $timestamp . '|' . $messageText . '|' . $imageUrl);

foreach ($chunks as $chunkFile) {
    if (!file_exists($chunkFile)) continue;
    $fp = fopen($chunkFile, 'r+');
    if (!$fp) continue;
    flock($fp, LOCK_EX);
    $json = stream_get_contents($fp);
    $messages = json_decode($json, true);
    if (!is_array($messages)) {
        flock($fp, LOCK_UN);
        fclose($fp);
        continue;
    }

    $modified = false;
    foreach ($messages as &$msg) {
        $mid = md5(($msg['username'] ?? '') . '|' . ($msg['timestamp'] ?? 0) . '|' . ($msg['message'] ?? '') . '|' . ($msg['image_url'] ?? ''));
        if ($mid === $msgId) {
            if ($action === 'unrecall') {
                unset($msg['recalled']);
                unset($msg['recalled_by']);
                unset($msg['recalled_at']);
            } else {
                $msg['recalled'] = true;
                $msg['recalled_by'] = $currentUser;
                $msg['recalled_at'] = time();
            }
            $modified = true;
            $found = true;
            break;
        }
    }
    unset($msg);

    if ($modified) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($messages, JSON_UNESCAPED_UNICODE));
    }

    flock($fp, LOCK_UN);
    fclose($fp);

    if ($found) {
        // 删除缓存文件，强制重建
        $cacheAll = $searchDir . '/.cache_all.json';
        $cacheSig = $searchDir . '/.cache_sig.json';
        if (file_exists($cacheAll)) unlink($cacheAll);
        if (file_exists($cacheSig)) unlink($cacheSig);
        break;
    }
}

if ($found) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => '未找到该消息']);
}
