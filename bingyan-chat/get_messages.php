<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    echo '<div style="text-align:center;padding:20px;color:#e53e3e;">请先登录</div>';
    exit();
}

$chatDir = 'chatlogs';
$currentUser = $_SESSION['username'];
$isAdminUser = isAdmin();

function formatMessage($text) {
    $text = htmlspecialchars($text);
    $text = preg_replace_callback('/```(\w*)\n?([\s\S]*?)```/', function($m) {
        $lang = $m[1] ?: 'code';
        return '<div class="code-block"><div class="code-header"><span>' . $lang . '</span><button class="copy-btn" onclick="copyCode(this)">复制</button></div><pre><code>' . $m[2] . '</code></pre></div>';
    }, $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    return $text;
}

// 获取分页参数
$messagesPerPage = 50;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 0) $page = 0;

// 获取所有分片文件，按名称排序（升序）
$chunks = glob($chatDir . '/chatlog_*.json');
sort($chunks);

// 如果没有分片文件，检查旧的单文件
if (empty($chunks)) {
    $oldFile = $chatDir . '/chatlog.json';
    if (file_exists($oldFile)) {
        $chunks = [$oldFile];
    }
}

if (empty($chunks)) {
    echo '<div style="text-align:center;padding:20px;color:#718096;">暂无聊天记录</div><span id="scrollTarget"></span>';
    exit();
}

// ------- 性能优化：缓存合并排序后的消息 -------
$cacheFile = $chatDir . '/.cache_all.json';
$sigFile   = $chatDir . '/.cache_sig.json';

// 计算当前分片签名
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
    $allMessages = [];
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

    if (empty($allMessages)) {
        echo '<div style="text-align:center;padding:20px;color:#718096;">暂无聊天记录</div><span id="scrollTarget"></span>';
        exit();
    }

    usort($allMessages, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });

    file_put_contents($cacheFile, json_encode($allMessages, JSON_UNESCAPED_UNICODE), LOCK_EX);
    file_put_contents($sigFile, json_encode($currentSig), LOCK_EX);
} else if (empty($allMessages)) {
    echo '<div style="text-align:center;padding:20px;color:#718096;">暂无聊天记录</div><span id="scrollTarget"></span>';
    exit();
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

// 分页：最新的消息在最后，所以倒序取
$totalMessages = count($allMessages);
$totalPages = ceil($totalMessages / $messagesPerPage);
if ($totalPages < 1) $totalPages = 1;

// page 0 表示最后一页
if ($page === 0 || $page > $totalPages) {
    $page = $totalPages;
}

// 计算当前页的消息范围
$start = ($page - 1) * $messagesPerPage;
$pageMessages = array_slice($allMessages, $start, $messagesPerPage);

// 增量刷新支持：如果 last_id 匹配，说明没有新消息
if (!empty($_GET['last_id']) && !empty($allMessages)) {
    $lastMsg = $allMessages[count($allMessages) - 1];
    $lastId = md5(($lastMsg['username'] ?? '') . ':' . ($lastMsg['message'] ?? '') . ':' . ($lastMsg['timestamp'] ?? 0) . ':' . ($lastMsg['image_url'] ?? ''));
    if ($_GET['last_id'] === $lastId) {
        echo 'NOCHANGE';
        exit();
    }
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
        $messageClass = $isCurrentUser ? 'message-self' : 'message-other';
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
    echo '<div class="message-meta">' . ($isAi ? 'AI助手' : formatUserDisplay($msg['username'])) . ' ' . $time;
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
    if (($isCurrentUser || $isAdminUser) && empty($msg['recalled']) && ($isAdminUser || time() - $msg['timestamp'] <= 120)) {
        $mid = md5($msg['username'] . '|' . $msg['timestamp'] . '|' . ($msg['message'] ?? '') . '|' . ($msg['image_url'] ?? ''));
        echo '<div class="recall-btn" data-mid="' . $mid . '" data-username="' . htmlspecialchars($msg['username']) . '" data-timestamp="' . $msg['timestamp'] . '" data-message="' . htmlspecialchars($msg['message'] ?? '') . '" data-image="' . htmlspecialchars($msg['image_url'] ?? '') . '" onclick="recallMessage(this)">撤回</div>';
    }
    if ($isAdminUser && !empty($msg['recalled'])) {
        $mid = md5($msg['username'] . '|' . $msg['timestamp'] . '|' . ($msg['message'] ?? '') . '|' . ($msg['image_url'] ?? ''));
        echo '<div class="recall-btn unrecall" data-mid="' . $mid . '" data-username="' . htmlspecialchars($msg['username']) . '" data-timestamp="' . $msg['timestamp'] . '" data-message="' . htmlspecialchars($msg['message'] ?? '') . '" data-image="' . htmlspecialchars($msg['image_url'] ?? '') . '" onclick="unrecallMessage(this)">取消撤回</div>';
    }
    echo '</div>';
    echo '</div>';
}

// 输出分页信息（供前端使用）
$lastId = '';
if (!empty($pageMessages)) {
    $lastIdx = count($pageMessages) - 1;
    $lastMsg = $pageMessages[$lastIdx];
    $lastId = md5(($lastMsg['username'] ?? '') . ':' . ($lastMsg['message'] ?? '') . ':' . ($lastMsg['timestamp'] ?? 0) . ':' . ($lastMsg['image_url'] ?? ''));
}
echo '<div id="pagination-info" data-page="' . $page . '" data-total-pages="' . $totalPages . '" data-total="' . $totalMessages . '" data-last-id="' . $lastId . '" style="display:none;"></div>';
echo '<span id="scrollTarget"></span>';
