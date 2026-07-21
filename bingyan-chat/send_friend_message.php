<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit();
}

$username = $_SESSION['username'];
$userData = getUserData($username);
if ($userData && !empty($userData['banned'])) {
    echo json_encode(['success' => false, 'error' => 'banned']);
    exit();
}
$aiSender = isset($_POST['ai_sender']) ? trim($_POST['ai_sender']) : '';
if ($aiSender === 'AI助手') {
    $username = 'AI助手';
}
$message = trim($_POST['message']);
$imageUrl = isset($_POST['image_url']) ? trim($_POST['image_url']) : '';
$friend = isset($_POST['friend']) ? trim($_POST['friend']) : '';

if (empty($friend)) {
    echo json_encode(['success' => false, 'error' => 'no_friend']);
    exit();
}

if (empty($message) && empty($imageUrl)) {
    echo json_encode(['success' => false, 'error' => 'empty']);
    exit();
}

if (mb_strlen($message) > 100 && $username !== 'AI助手') {
    echo json_encode(['success' => false, 'error' => 'toolong']);
    exit();
}

if (!empty($imageUrl)) {
    if (strpos($imageUrl, 'http://') !== 0 && strpos($imageUrl, 'https://') !== 0 && strpos($imageUrl, '/') !== 0) {
        echo json_encode(['success' => false, 'error' => 'invalid_image_url']);
        exit();
    }
}

$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

// 检查是否是好友
$dataDir = 'data';
$friendsFile = $dataDir . '/friends.json';
$friends = [];
if (file_exists($friendsFile)) {
    $friends = json_decode(file_get_contents($friendsFile), true);
    if (!is_array($friends)) $friends = [];
}

$isFriend = false;
foreach ($friends as $f) {
    if (($f['user1'] === $username && $f['user2'] === $friend) ||
        ($f['user1'] === $friend && $f['user2'] === $username)) {
        $isFriend = true;
        break;
    }
}

if (!$isFriend) {
    echo json_encode(['success' => false, 'error' => 'not_friend']);
    exit();
}

// 私聊消息存储：两个用户按字母排序生成唯一会话ID
$users = [$username, $friend];
sort($users);
$chatId = md5($users[0] . '_' . $users[1]);

$chatDir = 'chatlogs/private/' . $chatId;
if (!is_dir($chatDir)) {
    mkdir($chatDir, 0755, true);
}

$newMessage = array(
    'username' => $username,
    'message' => $message,
    'image_url' => $imageUrl,
    'timestamp' => time()
);

$chunkSize = 30 * 1024;
$chunks = glob($chatDir . '/chatlog_*.json');
sort($chunks);

$lastChunk = end($chunks);
$appendToExisting = false;

if ($lastChunk && filesize($lastChunk) < $chunkSize) {
    $appendToExisting = true;
}

if ($appendToExisting && $lastChunk) {
    $fp = fopen($lastChunk, 'r');
    if ($fp) {
        flock($fp, LOCK_SH);
        $json = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $messages = json_decode($json, true);
        if (!is_array($messages)) $messages = [];
    } else {
        $messages = [];
    }
    $messages[] = $newMessage;
    $fp = fopen($lastChunk, 'w');
    if (!$fp) {
        echo json_encode(['success' => false, 'error' => 'save']);
        exit();
    }
    flock($fp, LOCK_EX);
    fwrite($fp, json_encode($messages, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
} else {
    $chunkIndex = count($chunks) + 1;
    $newChunkFile = $chatDir . '/chatlog_' . $chunkIndex . '.json';
    $fp = fopen($newChunkFile, 'w');
    if (!$fp) {
        echo json_encode(['success' => false, 'error' => 'save']);
        exit();
    }
    flock($fp, LOCK_EX);
    fwrite($fp, json_encode([$newMessage], JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

// 清除缓存，下次读取时重建
$cacheFile = $chatDir . '/.cache_all.json';
if (file_exists($cacheFile)) unlink($cacheFile);
$sigFile = $chatDir . '/.cache_sig.json';
if (file_exists($sigFile)) unlink($sigFile);

// 更新用户消息计数
if ($username !== 'AI助手') {
    $userData = getUserData($username);
    if ($userData) {
        $userData['message_count'] = ($userData['message_count'] ?? 0) + 1;
        saveUserData($username, $userData);
    }
}

echo json_encode(['success' => true]);
