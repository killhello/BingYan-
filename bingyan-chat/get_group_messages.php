<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    echo '<div style="text-align:center;padding:20px;color:#e53e3e;">请先登录</div>';
    exit();
}

$currentUser = $_SESSION['username'];
$isAdminUser = isAdmin();
$groupId = isset($_GET['group_id']) ? $_GET['group_id'] : '';

function formatMessage($text) {
    $text = htmlspecialchars($text);
    $text = preg_replace_callback('/```(\w*)\n?([\s\S]*?)```/', function($m) {
        $lang = $m[1] ?: 'code';
        return '<div class="code-block"><div class="code-header"><span>' . $lang . '</span><button class="copy-btn" onclick="copyCode(this)">复制</button></div><pre><code>' . $m[2] . '</code></pre></div>';
    }, $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    return $text;
}

if (empty($groupId)) {
    echo '<div style="text-align:center;padding:20px;color:#e53e3e;">群号不能为空</div>';
    exit();
}

// 检查是否是群成员
$dataDir = 'data';
$membersFile = $dataDir . '/group_members.json';
$members = [];
if (file_exists($membersFile)) {
    $json = file_get_contents($membersFile);
    $members = json_decode($json, true);
    if (!is_array($members)) $members = [];
}

$isMember = false;
foreach ($members as $m) {
    if ($m['group_id'] === $groupId && $m['username'] === $currentUser) {
        $isMember = true;
        break;
    }
}

if (!$isMember) {
    echo '<div style="text-align:center;padding:20px;color:#e53e3e;">你不是该群成员</div>';
    exit();
}

$groupChatDir = 'chatlogs/groups/' . $groupId;
$messagesPerPage = 50;

$chunks = [];
$allMessages = [];
if (is_dir($groupChatDir)) {
    $chunks = glob($groupChatDir . '/chatlog_*.json');
    sort($chunks);
}

// ------- 性能优化：缓存合并排序后的消息 -------
if (!empty($chunks)) {
    $cacheFile = $groupChatDir . '/.cache_all.json';
    $sigFile   = $groupChatDir . '/.cache_sig.json';

    $sigData = [];
    foreach ($chunks as $f) {
        $sigData[] = basename($f) . ':' . filesize($f) . ':' . filemtime($f);
    }
    $currentSig = md5(implode('|', $sigData));

    $useCache = false;
    if (file_exists($cacheFile) && file_exists($sigFile)) {
        $storedSig = json_decode(file_get_contents($sigFile), true);
        if (is_string($storedSig) && $storedSig === $currentSig) {
            $allMessages = json_decode(file_get_contents($cacheFile), true);
            if (is_array($allMessages)) $useCache = true;
        }
    }

    if (!$useCache) {
        foreach ($chunks as $chunkFile) {
            if (!file_exists($chunkFile)) continue;
            $fp = fopen($chunkFile, 'r');
            if (!$fp) continue;
            flock($fp, LOCK_SH);
            $json = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            $messages = json_decode($json, true);
            if (is_array($messages)) {
                array_push($allMessages, ...$messages);
            }
        }

        usort($allMessages, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        file_put_contents($cacheFile, json_encode($allMessages, JSON_UNESCAPED_UNICODE), LOCK_EX);
        file_put_contents($sigFile, json_encode($currentSig), LOCK_EX);
    }
}

// 清理过期撤回消息（撤回超过60秒的从文件删除）
$expiredRemoved = false;
$allMessages = array_values(array_filter($allMessages, function($m) use (&$expiredRemoved) {
    if (!empty($m['recalled']) && !empty($m['recalled_at']) && time() - $m['recalled_at'] > 60) {
        $expiredRemoved = true;
        return false;
    }
    return true;
}));
if ($expiredRemoved) {
    foreach ($chunks as $chunkFile) {
        if (!file_exists($chunkFile)) continue;
        $fp = fopen($chunkFile, 'r+');
        if (!$fp) continue;
        flock($fp, LOCK_EX);
        $content = stream_get_contents($fp);
        $msgs = json_decode($content, true);
        if (!is_array($msgs)) { flock($fp, LOCK_UN); fclose($fp); continue; }
        $cleaned = array_values(array_filter($msgs, function($m) {
            return !(!empty($m['recalled']) && !empty($m['recalled_at']) && time() - $m['recalled_at'] > 60);
        }));
        if (count($cleaned) !== count($msgs)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($cleaned, JSON_UNESCAPED_UNICODE));
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    @unlink($cacheFile);
    @unlink($sigFile);
}

$totalMessages = count($allMessages);
$totalPages = ceil($totalMessages / $messagesPerPage);
if ($totalPages < 1) $totalPages = 1;

$page = isset($_GET['page']) ? intval($_GET['page']) : 0;
if ($page < 1) $page = $totalPages;
if ($page > $totalPages) $page = $totalPages;

$start = ($page - 1) * $messagesPerPage;
$pageMessages = array_slice($allMessages, $start, $messagesPerPage);

// 增量刷新支持
if (!empty($_GET['last_id']) && !empty($allMessages)) {
    $lastMsg = $allMessages[count($allMessages) - 1];
    $lastId = md5(($lastMsg['username'] ?? '') . ':' . ($lastMsg['message'] ?? '') . ':' . ($lastMsg['timestamp'] ?? 0) . ':' . ($lastMsg['image_url'] ?? ''));
    if ($_GET['last_id'] === $lastId) {
        echo 'NOCHANGE';
        exit();
    }
}

if (empty($pageMessages)) {
    echo '<div style="text-align:center;padding:20px;color:#718096;">暂无消息</div>';
    echo '<div id="pagination-info" data-page="' . $page . '" data-total-pages="' . $totalPages . '" data-total="' . $totalMessages . '" data-last-id="" style="display:none;"></div>';
    echo '<span id="scrollTarget"></span>';
    exit();
}

foreach ($pageMessages as $msg) {
    if (!isset($msg['username']) || !isset($msg['timestamp'])) {
        continue;
    }

    $isCurrentUser = ($msg['username'] === $currentUser);
    $isAi = ($msg['username'] === 'AI助手');
    if ($isAi) {
        $messageClass = 'message-ai';
        $avatarBg = '#9333ea';
    } else {
        $messageClass = $isCurrentUser ? 'message-right' : 'message-left';
        $avatarBg = $isCurrentUser ? '#22c55e' : '#48bb78';
    }
    $time = date('Y-m-d H:i', $msg['timestamp']);

    echo '<div class="message-item ' . $messageClass . '">';
    if ($isAi) {
        echo '<div class="avatar" style="background-color:#9333ea;">AI</div>';
    } else {
        $firstLetter = strtoupper(substr(htmlspecialchars($msg['username']), 0, 1));
        echo '<img src="avatar.php?name=' . urlencode($msg['username']) . '" class="avatar" style="object-fit:cover;" onerror="this.onerror=null;this.outerHTML=\'<div class=\\\'avatar\\\' style=\\\'background-color:' . $avatarBg . ';\\\'>' . $firstLetter . '</div>\'">';
    }
    echo '<div class="message-content-wrap">';
    echo '<div class="message-meta">' . formatUserDisplay($msg['username']) . ' ' . $time;
    if (($isCurrentUser || $isAdminUser) && !empty($msg['recalled'])) {
        echo ' <span class="recalled-tag">已撤回</span>';
    }
    echo '</div>';
    echo '<div class="message-bubble">';
    if (!empty($msg['recalled'])) {
        $recalledByAdmin = !empty($msg['recalled_by']) && isAdminUser($msg['recalled_by']);
        echo '<em class="recalled-msg">' . ($recalledByAdmin ? '已被管理员撤回' : '该消息已撤回') . '</em>';
    } else {
        if (!empty($msg['message'])) {
            echo formatMessage($msg['message']);
        }
        if (!empty($msg['image_url'])) {
            echo '<img class="chat-image" src="' . htmlspecialchars($msg['image_url']) . '" alt="图片" loading="lazy">';
        }
    }
    echo '</div>';
    $gid = $_GET['group_id'] ?? '';
    if (($isCurrentUser || $isAdminUser) && empty($msg['recalled']) && ($isAdminUser || time() - $msg['timestamp'] <= 120)) {
        $mid = md5($msg['username'] . '|' . $msg['timestamp'] . '|' . ($msg['message'] ?? '') . '|' . ($msg['image_url'] ?? ''));
        echo '<div class="recall-btn" data-mid="' . $mid . '" data-username="' . htmlspecialchars($msg['username']) . '" data-timestamp="' . $msg['timestamp'] . '" data-message="' . htmlspecialchars($msg['message'] ?? '') . '" data-image="' . htmlspecialchars($msg['image_url'] ?? '') . '" data-group="' . $groupId . '" onclick="recallMessage(this)">撤回</div>';
    }
    if ($isAdminUser && !empty($msg['recalled'])) {
        $mid = md5($msg['username'] . '|' . $msg['timestamp'] . '|' . ($msg['message'] ?? '') . '|' . ($msg['image_url'] ?? ''));
        echo '<div class="recall-btn unrecall" data-mid="' . $mid . '" data-username="' . htmlspecialchars($msg['username']) . '" data-timestamp="' . $msg['timestamp'] . '" data-message="' . htmlspecialchars($msg['message'] ?? '') . '" data-image="' . htmlspecialchars($msg['image_url'] ?? '') . '" data-group="' . $groupId . '" onclick="unrecallMessage(this)">取消撤回</div>';
    }
    echo '</div>';
    echo '</div>';
}

// 输出分页信息
$lastId = '';
if (!empty($pageMessages)) {
    $lastIdx = count($pageMessages) - 1;
    $lastMsg = $pageMessages[$lastIdx];
    $lastId = md5(($lastMsg['username'] ?? '') . ':' . ($lastMsg['message'] ?? '') . ':' . ($lastMsg['timestamp'] ?? 0) . ':' . ($lastMsg['image_url'] ?? ''));
}
echo '<div id="pagination-info" data-page="' . $page . '" data-total-pages="' . $totalPages . '" data-total="' . $totalMessages . '" data-last-id="' . $lastId . '" style="display:none;"></div>';
echo '<span id="scrollTarget"></span>';
