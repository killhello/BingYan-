<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$currentUser = $_SESSION['username'];
$isAdminUser = isAdmin();
$groupId = isset($_GET['group_id']) ? $_GET['group_id'] : '';

if (empty($groupId)) {
    header('Location: group_manager.php');
    exit();
}

$dataDir = 'data';
$groupsFile = $dataDir . '/groups.json';
$membersFile = $dataDir . '/group_members.json';

function readJson($file) {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

$groups = readJson($groupsFile);
$members = readJson($membersFile);

// 查找群聊
$group = null;
foreach ($groups as $g) {
    if ($g['group_id'] === $groupId) {
        $group = $g;
        break;
    }
}

if (!$group) {
    header('Location: group_manager.php');
    exit();
}

// 检查是否是群成员
$isMember = false;
$myRole = '';
foreach ($members as $m) {
    if ($m['group_id'] === $groupId && $m['username'] === $currentUser) {
        $isMember = true;
        $myRole = $m['role'];
        break;
    }
}

if (!$isMember) {
    header('Location: group_manager.php');
    exit();
}

// 获取群成员列表
$groupMembers = [];
foreach ($members as $m) {
    if ($m['group_id'] === $groupId) {
        $groupMembers[] = $m;
    }
}

// 读取群聊消息（分页）
$groupChatDir = 'chatlogs/groups/' . $groupId;
$messagesPerPage = 50;

$allMessages = [];
if (is_dir($groupChatDir)) {
    $chunks = glob($groupChatDir . '/chatlog_*.json');
    sort($chunks);
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
            $allMessages = array_merge($allMessages, $messages);
        }
    }
}

usort($allMessages, function($a, $b) {
    return $a['timestamp'] - $b['timestamp'];
});

$totalMessages = count($allMessages);
$totalPages = ceil($totalMessages / $messagesPerPage);
if ($totalPages < 1) $totalPages = 1;

$page = isset($_GET['page']) ? intval($_GET['page']) : 0;
if ($page < 1) $page = $totalPages;
if ($page > $totalPages) $page = $totalPages;

$start = ($page - 1) * $messagesPerPage;
$pageMessages = array_slice($allMessages, $start, $messagesPerPage);
$username = $currentUser;

function formatMessage($text) {
    $text = htmlspecialchars($text);
    $text = preg_replace_callback('/```(\w*)\n?([\s\S]*?)```/', function($m) {
        $lang = $m[1] ?: 'code';
        return '<div class="code-block"><div class="code-header"><span>' . $lang . '</span><button class="copy-btn" onclick="copyCode(this)">复制</button></div><pre><code>' . $m[2] . '</code></pre></div>';
    }, $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    return $text;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($group['name']); ?> - 群聊</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: rgb(34,193,195);
            background: linear-gradient(0deg, rgba(34,193,195,1) 0%, rgba(253,187,45,1) 100%);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.8);
            color: #1a1a2e;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .chat-header h1 { font-size: 18px; font-weight: 700; }
        .chat-header p { font-size: 12px; color: #64748b; }
        .header-actions a {
            color: #475569;
            text-decoration: none;
            margin-left: 10px;
            padding: 6px 14px;
            background: rgba(255,255,255,0.6);
            border: 1px solid rgba(0,0,0,0.06);
            border-radius: 20px;
            font-size: 13px;
            transition: all 0.3s;
        }
        .header-actions a:hover { background: rgba(255,255,255,0.9); border-color: rgba(0,0,0,0.1); }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            padding-bottom: 100px;
        }
        .message-item {
            display: flex;
            margin-bottom: 15px;
            max-width: 70%;
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message-left { margin-right: auto; }
        .message-right { margin-left: auto; flex-direction: row-reverse; }
        .message-right .message-content-wrap { align-items: flex-end; }
        .message-right .message-bubble {
            background: rgba(34,197,94,0.15);
            border: 1px solid rgba(34,197,94,0.2);
            color: #000;
            border-bottom-right-radius: 4px;
        }
        .message-left .message-bubble {
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(255,255,255,0.9);
            color: #000;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .message-ai .message-bubble {
            background: rgba(147,51,234,0.08);
            color: #000;
            border: 1px solid rgba(147,51,234,0.15);
            border-bottom-left-radius: 4px;
        }
        .message-ai .message-meta { color: #000; font-weight: 500; }
        .ai-thinking {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #9333ea;
            font-size: 13px;
            padding: 8px 15px;
        }
        .ai-thinking-dots span {
            display: inline-block;
            width: 6px;
            height: 6px;
            background: #9333ea;
            border-radius: 50%;
            animation: ai-dot 1.4s infinite ease-in-out both;
            margin: 0 2px;
        }
        .ai-thinking-dots span:nth-child(1) { animation-delay: -0.32s; }
        .ai-thinking-dots span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes ai-dot {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
            margin: 0 10px;
            flex-shrink: 0;
        }
        .message-content-wrap {
            display: flex;
            flex-direction: column;
        }
        .message-meta {
            font-size: 12px;
            color: #000;
            margin-bottom: 4px;
            padding: 0 5px;
        }
        .message-right .message-meta { text-align: right; }
        .message-bubble {
            padding: 10px 15px;
            border-radius: 10px;
            word-wrap: break-word;
            line-height: 1.5;
            font-size: 14px;
            min-height: 20px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .chat-input-fixed {
            position: fixed;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 32px);
            max-width: 768px;
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.8);
            border-radius: 20px;
            padding: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            z-index: 100;
        }
        .chat-form { width: 100%; }
        .chat-input-container {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        #messageInput {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 14px;
            font-size: 14px;
            resize: none;
            height: 48px;
            outline: none;
            background: rgba(255,255,255,0.8);
            color: #1e293b;
            transition: all 0.3s;
        }
        #messageInput::placeholder { color: #94a3b8; }
        #messageInput:focus { border-color: rgba(102,126,234,0.3); background: white; }
        .send-btn {
            padding: 0 24px;
            height: 48px;
            background: rgba(102,126,234,0.12);
            color: #6366f1;
            border: 1px solid rgba(102,126,234,0.2);
            border-radius: 14px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            backdrop-filter: blur(8px);
        }
        .send-btn:hover {
            background: rgba(102,126,234,0.2);
            border-color: rgba(102,126,234,0.3);
            transform: scale(1.02);
        }
        .send-btn:disabled { opacity: 0.3; cursor: not-allowed; transform: none; }
        .status {
            position: fixed;
            bottom: 90px;
            left: 50%;
            transform: translateX(-50%);
            padding: 10px 24px;
            border-radius: 14px;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
            z-index: 99;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            animation: status-in 0.3s ease;
        }
        @keyframes status-in {
            from { opacity: 0; transform: translateX(-50%) translateY(10px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        .status.success {
            background: rgba(34,197,94,0.12);
            color: #16a34a;
            border: 1px solid rgba(34,197,94,0.15);
        }
        .status.error {
            background: rgba(239,68,68,0.1);
            color: #dc2626;
            border: 1px solid rgba(239,68,68,0.15);
        }

        /* 图片上传相关样式 */
        .upload-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 14px;
            cursor: pointer;
            font-size: 20px;
            color: #94a3b8;
            background: rgba(255,255,255,0.5);
            transition: all 0.3s;
            flex-shrink: 0;
        }
        .upload-btn:hover {
            border-color: rgba(66,153,225,0.3);
            color: #4299e1;
            background: rgba(66,153,225,0.08);
        }
        .upload-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        #imageInput { display: none; }
        .image-preview-container {
            display: none;
            padding: 10px 0;
        }
        .image-preview-container.active { display: block; }
        .image-preview-wrap {
            position: relative;
            display: inline-block;
            max-width: 200px;
        }
        .image-preview-wrap img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.06);
        }
        .image-preview-remove {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(239,68,68,0.6);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            line-height: 24px;
            text-align: center;
            backdrop-filter: blur(8px);
        }
        .image-preview-remove:hover { background: rgba(239,68,68,0.8); }
        .upload-progress {
            display: none;
            padding: 4px 0;
        }
        .upload-progress.active { display: block; }
        .upload-progress-bar {
            height: 3px;
            background: rgba(0,0,0,0.08);
            border-radius: 2px;
            overflow: hidden;
        }
        .upload-progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #4299e1, #6366f1);
            border-radius: 2px;
            transition: width 0.3s;
        }
        .upload-progress-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        /* 聊天气泡中的图片样式 */
        .message-bubble img.chat-image {
            max-width: 250px;
            max-height: 200px;
            border-radius: 6px;
            cursor: pointer;
            display: block;
        }
        .message-bubble img.chat-image:hover { opacity: 0.9; }
        .message-bubble img.chat-image:first-child { margin-top: 0; }
        .message-bubble img.chat-image:not(:first-child) { margin-top: 5px; }
        /* 图片灯箱 */
        .image-lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }
        .image-lightbox.active { display: flex; }
        .image-lightbox img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 12px;
            box-shadow: 0 24px 80px rgba(0,0,0,0.2);
        }
        .image-lightbox-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: rgba(0,0,0,0.3);
            font-size: 36px;
            cursor: pointer;
            z-index: 10000;
            transition: all 0.3s;
        }
        .image-lightbox-close:hover { color: rgba(0,0,0,0.6); transform: rotate(90deg); }

        /* 代码块样式 */
        .code-block {
            background: rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 8px;
            overflow: hidden;
            margin: 8px 0;
        }
        .code-block .code-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 12px;
            background: rgba(0,0,0,0.03);
            border-bottom: 1px solid rgba(0,0,0,0.06);
            font-size: 12px;
            color: #64748b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .code-block .code-header .copy-btn {
            background: none;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 11px;
            cursor: pointer;
            color: #64748b;
            transition: all 0.2s;
        }
        .code-block .code-header .copy-btn:hover {
            background: rgba(0,0,0,0.06);
            color: #1e293b;
        }
        .code-block pre {
            margin: 0;
            padding: 12px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.5;
        }
        .code-block code {
            font-family: "SF Mono", Monaco, "Cascadia Code", "Consolas", monospace;
            color: #334155;
        }

        /* 撤回相关 */
.recalled-msg {
    font-style: normal;
    color: #ef4444;
    font-size: 13px;
}
        .recalled-tag {
            color: #94a3b8;
            font-size: 11px;
            margin-left: 4px;
        }
        .message-right .recall-btn {
            display: none;
            font-size: 11px;
            color: #6366f1;
            cursor: pointer;
            text-align: right;
            padding: 2px 5px 0;
            transition: color 0.2s;
        }
        .message-right .recall-btn:hover { color: #dc2626; }
        .message-right:hover .recall-btn { display: block; }
        .message-right .recall-btn.unrecall {
            display: none;
            font-size: 11px;
            color: #f59e0b;
            cursor: pointer;
            text-align: right;
            padding: 2px 5px 0;
            transition: color 0.2s;
        }
        .message-right .recall-btn.unrecall:hover { color: #dc2626; }
        .message-right:hover .recall-btn.unrecall { display: block; }

        /* ===== Entrance Animation ===== */
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-60px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(60px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .animate-in {
            will-change: transform, opacity;
        }
        .chat-header.animate-in,
        .message-left.animate-in,
        .sidebar-toggle.animate-in {
            animation: slideInLeft 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
        }
        .message-right.animate-in,
        .message-ai.animate-in {
            animation: slideInRight 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
        }
        .user-level-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            color: #6b7280;
            background: rgba(107,114,128,0.1);
            padding: 0 5px;
            border-radius: 8px;
            margin-left: 3px;
            vertical-align: middle;
        }
.user-title-badge {
    display: inline-block;
    font-size: 14px;
    font-weight: 500;
    color: #6366f1;
    margin-left: 3px;
    vertical-align: middle;
}
.level-img {
    width: 60px;
    height: 48px;
    object-fit: cover;
    object-position: center top;
    vertical-align: middle;
    border-radius: 2px;
}
@media (max-width: 768px) {
            .message-item { max-width: 90%; }
            .chat-input-container { padding: 0 5px; }
            #messageInput { height: 45px; padding: 10px 12px; }
            .send-btn { padding: 0 20px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar_component.php'; ?>
    <div class="chat-header">
        <div>
            <h1><?php echo htmlspecialchars($group['name']); ?></h1>
            <p>群号：<?php echo $group['group_id']; ?> | 成员：<?php echo count($groupMembers); ?>人 | 我的身份：<?php echo $myRole === 'admin' ? '管理员' : '成员'; ?></p>
        </div>
        <div class="header-actions">
            <?php if ($myRole === 'admin'): ?>
                <a href="group_admin.php?group_id=<?php echo $groupId; ?>">群管理</a>
                <a href="group_manager.php?action=disband&group_id=<?php echo $groupId; ?>" onclick="return confirm('确定要解散该群聊吗？此操作不可撤销！')" style="color:#dc2626;">解散群聊</a>
            <?php endif; ?>
            <a href="group_manager.php?action=leave&group_id=<?php echo $groupId; ?>" onclick="return confirm('确定要退出该群聊吗？')">退出群聊</a>
            <a href="group_manager.php">群聊列表</a>
            <a href="chat.php">公共聊天室</a>
        </div>
    </div>

    <div class="chat-messages" id="chatMessages">
        <?php if (empty($pageMessages)): ?>
            <div style="text-align:center;padding:20px;color:#94a3b8;">暂无消息，快来发送第一条消息吧！</div>
        <?php else: ?>
            <?php foreach ($pageMessages as $msg): ?>
                <?php
                if (!isset($msg['username']) || !isset($msg['timestamp'])) continue;
                $isCurrentUser = ($msg['username'] === $currentUser);
                $isAi = ($msg['username'] === 'AI助手');
                if ($isAi) {
                    $messageClass = 'message-ai';
                    $avatarBg = '#9333ea';
                } else {
                    $messageClass = $isCurrentUser ? 'message-right' : 'message-left';
                    $avatarBg = $isCurrentUser ? '#4299e1' : '#48bb78';
                }
                $time = date('Y-m-d H:i', $msg['timestamp']);
                ?>
                <div class="message-item <?php echo $messageClass; ?>">
                    <?php if ($isAi): ?>
                        <div class="avatar" style="background-color:#9333ea;">AI</div>
                    <?php else: ?>
                        <?php $firstLetter = strtoupper(substr(htmlspecialchars($msg['username']), 0, 1)); ?>
                        <img src="avatar.php?name=<?php echo urlencode($msg['username']); ?>" class="avatar" style="object-fit:cover;" onerror="this.onerror=null;this.outerHTML='<div class=\'avatar\' style=\'background-color:<?php echo $avatarBg; ?>\'><?php echo $firstLetter; ?></div>'">
                    <?php endif; ?>
                    <div class="message-content-wrap">
                        <div class="message-meta"><?php echo $isAi ? 'AI助手' : formatUserDisplay($msg['username']); ?> <?php echo $time; ?><?php if (($isCurrentUser || $isAdminUser) && !empty($msg['recalled'])): ?> <span class="recalled-tag">已撤回</span><?php endif; ?></div>
                        <div class="message-bubble">
                            <?php if (!empty($msg['recalled'])): ?>
                                <?php $recalledByAdmin = !empty($msg['recalled_by']) && isAdminUser($msg['recalled_by']); ?>
                                <em class="recalled-msg"><?php echo $recalledByAdmin ? '已被管理员撤回' : '该消息已撤回'; ?></em>
                            <?php else: ?>
                                <?php if (!empty($msg['message'])): ?>
                                    <?php echo formatMessage($msg['message']); ?>
                                <?php endif; ?>
                                <?php if (!empty($msg['image_url'])): ?>
                                    <img class="chat-image" src="<?php echo htmlspecialchars($msg['image_url']); ?>" alt="图片" loading="lazy">
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (($isCurrentUser || $isAdminUser) && empty($msg['recalled']) && ($isAdminUser || time() - $msg['timestamp'] <= 120)): ?>
                            <?php $mid = md5($msg['username'] . '|' . $msg['timestamp'] . '|' . ($msg['message'] ?? '') . '|' . ($msg['image_url'] ?? '')); ?>
                            <div class="recall-btn" data-mid="<?php echo $mid; ?>" data-username="<?php echo htmlspecialchars($msg['username']); ?>" data-timestamp="<?php echo $msg['timestamp']; ?>" data-message="<?php echo htmlspecialchars($msg['message'] ?? ''); ?>" data-image="<?php echo htmlspecialchars($msg['image_url'] ?? ''); ?>" data-group="<?php echo $groupId; ?>" onclick="recallMessage(this)">撤回</div>
                        <?php endif; ?>
                        <?php if ($isAdminUser && !empty($msg['recalled'])): ?>
                            <?php $mid = md5($msg['username'] . '|' . $msg['timestamp'] . '|' . ($msg['message'] ?? '') . '|' . ($msg['image_url'] ?? '')); ?>
                            <div class="recall-btn unrecall" data-mid="<?php echo $mid; ?>" data-username="<?php echo htmlspecialchars($msg['username']); ?>" data-timestamp="<?php echo $msg['timestamp']; ?>" data-message="<?php echo htmlspecialchars($msg['message'] ?? ''); ?>" data-image="<?php echo htmlspecialchars($msg['image_url'] ?? ''); ?>" data-group="<?php echo $groupId; ?>" onclick="unrecallMessage(this)">取消撤回</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php
        $lastId = '';
        if (!empty($pageMessages)) {
            $lastIdx = count($pageMessages) - 1;
            $lastMsg = $pageMessages[$lastIdx];
            $lastId = md5(($lastMsg['username'] ?? '') . ':' . ($lastMsg['message'] ?? '') . ':' . ($lastMsg['timestamp'] ?? 0) . ':' . ($lastMsg['image_url'] ?? ''));
        }
        ?>
        <div id="pagination-info" data-page="<?php echo $page; ?>" data-total-pages="<?php echo $totalPages; ?>" data-total="<?php echo $totalMessages; ?>" data-last-id="<?php echo $lastId; ?>" style="display:none;"></div>
        <span id="scrollTarget"></span>
    </div>

    <div class="chat-input-fixed">
        <div class="image-preview-container" id="imagePreviewContainer">
            <div class="image-preview-wrap">
                <img id="imagePreview" src="" alt="预览">
                <button type="button" class="image-preview-remove" id="removeImage" title="移除图片">&times;</button>
            </div>
        </div>
        <div class="upload-progress" id="uploadProgress">
            <div class="upload-progress-bar">
                <div class="upload-progress-fill" id="uploadProgressFill"></div>
            </div>
            <div class="upload-progress-text" id="uploadProgressText">上传中 0%</div>
        </div>

        <form class="chat-form" id="chatForm">
            <div class="chat-input-container">
                <label class="upload-btn" id="uploadBtn" title="上传图片">
                    &#128247;
                    <input type="file" id="imageInput" accept="image/jpeg,image/png">
                </label>
                <textarea
                    id="messageInput"
                    name="message"
                    placeholder="请输入聊天内容（最多100个字符）..."
                ></textarea>
                <button type="submit" class="send-btn" id="sendBtn">发送</button>
            </div>
        </form>
    </div>

    <div id="statusContainer"></div>

    <div class="image-lightbox" id="imageLightbox">
        <span class="image-lightbox-close" id="lightboxClose">&times;</span>
        <img id="lightboxImage" src="" alt="大图">
    </div>

    <script>
    var currentUser = <?php echo json_encode($currentUser); ?>;
    <?php $curLevelTitle = getUserLevelAndTitle($currentUser); ?>
    var userLevel = <?php echo json_encode($curLevelTitle['level']); ?>;
    var userTitle = <?php echo json_encode($curLevelTitle['title']); ?>;
    let refreshTimer;
    let pendingImageUrl = null;
    let currentPage = <?php echo $page; ?>;
    let totalPages = <?php echo $totalPages; ?>;
    let isLoadingMore = false;
    let hasMoreMessages = <?php echo $totalPages > 1 ? 'true' : 'false'; ?>;
    let lastMessageId = '';
    const groupId = '<?php echo $groupId; ?>';

    function showStatus(msg, type) {
        var container = document.getElementById('statusContainer');
        var el = document.createElement('div');
        el.className = 'status ' + type;
        el.textContent = msg;
        container.appendChild(el);
        setTimeout(function() {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.5s';
            setTimeout(function() { el.remove(); }, 500);
        }, 2000);
    }

    // 图片上传相关
    var imageInput = document.getElementById('imageInput');
    var imagePreviewContainer = document.getElementById('imagePreviewContainer');
    var imagePreview = document.getElementById('imagePreview');
    var removeImageBtn = document.getElementById('removeImage');
    var uploadBtn = document.getElementById('uploadBtn');
    var uploadProgress = document.getElementById('uploadProgress');
    var uploadProgressFill = document.getElementById('uploadProgressFill');
    var uploadProgressText = document.getElementById('uploadProgressText');

    imageInput.addEventListener('change', function() {
        var file = this.files[0];
        if (!file) return;

        if (!file.type.match(/^image\/(jpeg|png)$/)) {
            showStatus('仅支持 JPG/PNG 格式图片', 'error');
            this.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            showStatus('图片大小不能超过5MB', 'error');
            this.value = '';
            return;
        }

        var reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            imagePreviewContainer.classList.add('active');
        };
        reader.readAsDataURL(file);

        uploadImage(file);
    });

    removeImageBtn.addEventListener('click', function() {
        pendingImageUrl = null;
        imagePreviewContainer.classList.remove('active');
        imagePreview.src = '';
        imageInput.value = '';
        uploadProgress.classList.remove('active');
        uploadProgressFill.style.width = '0%';
    });

    function uploadImage(file) {
        uploadBtn.disabled = true;
        uploadProgress.classList.add('active');
        uploadProgressFill.style.width = '0%';
        uploadProgressText.textContent = '上传中 0%';

        var formData = new FormData();
        formData.append('image', file);

        var xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var percent = Math.round((e.loaded / e.total) * 100);
                uploadProgressFill.style.width = percent + '%';
                uploadProgressText.textContent = '上传中 ' + percent + '%';
            }
        });

        xhr.onload = function() {
            uploadBtn.disabled = false;
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    var imgUrl = '';
                    if (data.status === 'success' && data.data && data.data.public_url) {
                        imgUrl = data.data.public_url;
                    } else if (data.result === 'success' && data.url) {
                        imgUrl = data.url;
                    } else if (data.status === true && data.data && data.data.links && data.data.links.url) {
                        imgUrl = data.data.links.url;
                    }
                    if (imgUrl) {
                        pendingImageUrl = imgUrl;
                        uploadProgressText.textContent = '上传成功';
                        uploadProgressFill.style.width = '100%';
                        setTimeout(function() {
                            uploadProgress.classList.remove('active');
                        }, 1000);
                        showStatus('图片上传成功，点击发送按钮发送', 'success');
                    } else {
                        showStatus('图片上传失败: ' + (data.message || data.msg || '未知错误'), 'error');
                        resetUpload();
                    }
                } catch(e) {
                    showStatus('图片上传失败: 解析错误', 'error');
                    resetUpload();
                }
            } else {
                showStatus('图片上传失败: 网络错误', 'error');
                resetUpload();
            }
        };

        xhr.onerror = function() {
            uploadBtn.disabled = false;
            showStatus('图片上传失败: 网络错误', 'error');
            resetUpload();
        };

        xhr.open('POST', 'upload_image.php', true);
        xhr.send(formData);
    }

    function resetUpload() {
        pendingImageUrl = null;
        imagePreviewContainer.classList.remove('active');
        imagePreview.src = '';
        imageInput.value = '';
        uploadProgress.classList.remove('active');
        uploadProgressFill.style.width = '0%';
    }

    // 灯箱功能
    document.getElementById('imageLightbox').addEventListener('click', function(e) {
        if (e.target === this || e.target.id === 'lightboxClose') {
            this.classList.remove('active');
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('chat-image')) {
            var lightbox = document.getElementById('imageLightbox');
            document.getElementById('lightboxImage').src = e.target.src;
            lightbox.classList.add('active');
        }
    });

    document.getElementById('messageInput').addEventListener('input', function() {
        // 检测 @ai 逻辑
    });

    // 发送消息
    document.getElementById('chatForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var input = document.getElementById('messageInput');
        var value = input.value.trim();

        if (!value && !pendingImageUrl) {
            showStatus('请输入聊天内容或选择图片！', 'error');
            input.focus();
            return;
        }

        if (value && value.length > 100) {
            showStatus('消息长度不能超过100个字符！', 'error');
            input.focus();
            return;
        }

        var sendBtn = document.getElementById('sendBtn');
        sendBtn.disabled = true;

        var isAiCommand = value.indexOf('@ai') === 0;
        var aiPrompt = '';
        if (isAiCommand) {
            aiPrompt = value.substring(3).trim();
            if (!aiPrompt && !pendingImageUrl) {
                showStatus('请输入AI提示词！', 'error');
                sendBtn.disabled = false;
                input.focus();
                return;
            }
        }

        // 乐观渲染
        var sentMessage = value;
        var sentImage = pendingImageUrl;
        input.value = '';
        resetUpload();
        appendGroupMessage(currentUser, sentMessage, sentImage);

        var formData = new FormData();
        formData.append('message', value);
        formData.append('group_id', groupId);
        if (sentImage) {
            formData.append('image_url', sentImage);
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'send_group_message.php', true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        showStatus('发送成功', 'success');
                        refreshCurrentPage();
                        if (isAiCommand) {
                            callAiAndReply(aiPrompt);
                        }
                    } else {
                        removeLastGroupMessage(currentUser, sentMessage, sentImage);
                        showStatus('发送失败: ' + data.error, 'error');
                    }
                } catch(e) {
                    removeLastGroupMessage(currentUser, sentMessage, sentImage);
                    showStatus('发送失败', 'error');
                }
            } else {
                removeLastGroupMessage(currentUser, sentMessage, sentImage);
                showStatus('发送失败', 'error');
            }
            if (!isAiCommand) {
                sendBtn.disabled = false;
                input.focus();
            }
        };
        xhr.onerror = function() {
            removeLastGroupMessage(currentUser, sentMessage, sentImage);
            showStatus('网络错误', 'error');
            sendBtn.disabled = false;
        };
        xhr.send(formData);
    });

    // 回车发送消息
    var messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('chatForm').dispatchEvent(new Event('submit'));
            }
        });
    }

    // 调用AI并保存回复
    function callAiAndReply(prompt) {
        var sendBtn = document.getElementById('sendBtn');
        var messagesContainer = document.getElementById('chatMessages');

        var thinkingDiv = document.createElement('div');
        thinkingDiv.id = 'ai-thinking-' + Date.now();
        thinkingDiv.className = 'message-item message-ai';
        thinkingDiv.innerHTML = '<div class="avatar" style="background-color:#9333ea;">AI</div>' +
            '<div class="message-content-wrap">' +
            '<div class="message-meta">AI助手</div>' +
            '<div class="message-bubble ai-thinking">思考中<div class="ai-thinking-dots"><span></span><span></span><span></span></div></div>' +
            '</div>';
        messagesContainer.appendChild(thinkingDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        var aiXhr = new XMLHttpRequest();
        aiXhr.open('POST', 'ai_proxy.php', true);
        aiXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        aiXhr.onload = function() {
            thinkingDiv.remove();
            if (aiXhr.status === 200) {
                try {
                    var aiData = JSON.parse(aiXhr.responseText);
                    if (aiData.success) {
                        var savedAiContent = aiData.content;
                        appendGroupMessage('AI助手', savedAiContent, '');

                        var aiForm = new FormData();
                        aiForm.append('message', aiData.content);
                        aiForm.append('ai_sender', 'AI助手');
                        aiForm.append('group_id', groupId);

                        var saveXhr = new XMLHttpRequest();
                        saveXhr.open('POST', 'send_group_message.php', true);
                        saveXhr.onload = function() {
                            if (saveXhr.status === 200) {
                                refreshCurrentPage();
                            }
                            sendBtn.disabled = false;
                            document.getElementById('messageInput').focus();
                        };
                        saveXhr.onerror = function() {
                            sendBtn.disabled = false;
                            showStatus('AI回复保存失败', 'error');
                        };
                        saveXhr.send(aiForm);
                    } else {
                        sendBtn.disabled = false;
                        showStatus('AI请求失败: ' + (aiData.error || '未知错误'), 'error');
                    }
                } catch(e) {
                    sendBtn.disabled = false;
                    showStatus('AI响应解析失败', 'error');
                }
            } else {
                sendBtn.disabled = false;
                showStatus('AI请求失败: HTTP ' + aiXhr.status, 'error');
            }
        };
        aiXhr.onerror = function() {
            thinkingDiv.remove();
            sendBtn.disabled = false;
            showStatus('AI请求网络错误', 'error');
        };
        aiXhr.send('prompt=' + encodeURIComponent(prompt));
    }

    function appendGroupMessage(username, message, imageUrl) {
        var container = document.getElementById('chatMessages');
        var now = new Date();
        var time = now.getFullYear() + '-' +
            ('0' + (now.getMonth() + 1)).slice(-2) + '-' +
            ('0' + now.getDate()).slice(-2) + ' ' +
            ('0' + now.getHours()).slice(-2) + ':' +
            ('0' + now.getMinutes()).slice(-2);
        var isCurrentUser = (username === currentUser);
        var isAi = (username === 'AI助手');
        var msgClass = isAi ? 'message-ai' : (isCurrentUser ? 'message-right' : 'message-left');

        var div = document.createElement('div');
        div.className = 'message-item ' + msgClass;

        var avatarHtml;
        if (isAi) {
            avatarHtml = '<div class="avatar" style="background-color:#9333ea;">AI</div>';
        } else {
            var firstLetter = username.charAt(0).toUpperCase();
            var avatarColor = isCurrentUser ? '#4299e1' : '#48bb78';
            avatarHtml = '<div class="avatar" style="background-color:' + avatarColor + ';">' + firstLetter + '</div>';
        }

        var emptyEl = container.querySelector('div[style*="text-align:center"]');
        if (emptyEl && emptyEl.textContent.indexOf('暂无') !== -1) emptyEl.remove();

        div.innerHTML = avatarHtml +
            '<div class="message-content-wrap">' +
            '<div class="message-meta">' + escapeHtml(username) + ' <span class="user-level-badge">' + (isCurrentUser ? userLevel : 'Lv?') + '</span>' + (isCurrentUser && userTitle ? ' <span class="user-title-badge">[' + escapeHtml(userTitle) + ']</span>' : '') + ' ' + time + '</div>' +
            '<div class="message-bubble">' +
            (message ? formatMessage(message) : '') +
            (imageUrl ? '<img class="chat-image" src="' + escapeHtml(imageUrl) + '" alt="图片" loading="lazy">' : '') +
            '</div></div>';
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    function removeLastGroupMessage(username) {
        var container = document.getElementById('chatMessages');
        var items = container.querySelectorAll('.message-item');
        for (var i = items.length - 1; i >= 0; i--) {
            var item = items[i];
            var meta = item.querySelector('.message-meta');
            if (meta && meta.textContent.indexOf(username) === 0) {
                item.remove();
                break;
            }
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatMessage(text) {
        text = escapeHtml(text);
        text = text.replace(/```(\w*)\n?([\s\S]*?)```/g, function(match, lang, code) {
            var langLabel = lang || 'code';
            return '<div class="code-block"><div class="code-header"><span>' + langLabel + '</span><button class="copy-btn" onclick="copyCode(this)">复制</button></div><pre><code>' + code + '</code></pre></div>';
        });
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        return text;
    }

    function copyCode(btn) {
        var code = btn.parentElement.nextElementSibling.textContent;
        var textarea = document.createElement('textarea');
        textarea.value = code;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        btn.textContent = '已复制';
        setTimeout(function() { btn.textContent = '复制'; }, 2000);
    }

    function recallMessage(el) {
        var username = el.getAttribute('data-username');
        var timestamp = el.getAttribute('data-timestamp');
        var message = el.getAttribute('data-message');
        var image = el.getAttribute('data-image');
        var group = el.getAttribute('data-group') || '';

        var formData = new FormData();
        formData.append('username', username);
        formData.append('timestamp', timestamp);
        formData.append('message', message);
        formData.append('image_url', image);
        if (group) formData.append('group_id', group);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'recall_message.php', true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        showStatus('已撤回', 'success');
                        refreshCurrentPage(true);
                    } else {
                        showStatus(data.error || '撤回失败', 'error');
                    }
                } catch(e) {
                    showStatus('撤回失败', 'error');
                }
            } else {
                showStatus('网络错误', 'error');
            }
        };
        xhr.onerror = function() {
            showStatus('网络错误', 'error');
        };
        xhr.send(formData);
    }

    function unrecallMessage(el) {
        var username = el.getAttribute('data-username');
        var timestamp = el.getAttribute('data-timestamp');
        var message = el.getAttribute('data-message');
        var image = el.getAttribute('data-image');
        var group = el.getAttribute('data-group') || '';

        var formData = new FormData();
        formData.append('username', username);
        formData.append('timestamp', timestamp);
        formData.append('message', message);
        formData.append('image_url', image);
        if (group) formData.append('group_id', group);
        formData.append('action', 'unrecall');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'recall_message.php', true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        showStatus('已取消撤回', 'success');
                        refreshCurrentPage(true);
                    } else {
                        showStatus(data.error || '操作失败', 'error');
                    }
                } catch(e) {
                    showStatus('操作失败', 'error');
                }
            } else {
                showStatus('网络错误', 'error');
            }
        };
        xhr.onerror = function() {
            showStatus('网络错误', 'error');
        };
        xhr.send(formData);
    }

    // 初始化分页信息
    var initPagInfo = document.querySelector('#pagination-info');
    if (initPagInfo) {
        lastMessageId = initPagInfo.getAttribute('data-last-id') || '';
    }

    window.onload = function() {
        var messagesContainer = document.getElementById('chatMessages');

        document.getElementById('messageInput').focus();

        messagesContainer.addEventListener('scroll', function() {
            if (messagesContainer.scrollTop < 50 && !isLoadingMore && hasMoreMessages) {
                loadMoreMessages();
            }
        });

        clearInterval(refreshTimer);
        refreshTimer = setInterval(function() {
            refreshCurrentPage();
        }, 3000);
    };

    var entranceDone = false;

    function animateEntrance() {
        if (entranceDone) return;
        entranceDone = true;
        var items = document.querySelectorAll('.chat-header, .chat-messages > .message-item, .sidebar-toggle');
        if (!items.length) return;
        items.forEach(function(el, i) {
            el.classList.add('animate-in');
            el.style.animationDelay = (i * 60) + 'ms';
        });
    }
    setTimeout(function() {
        var st = document.getElementById('scrollTarget');
        if (st) st.scrollIntoView();
    }, 200);

    function loadMoreMessages() {
        if (isLoadingMore || !hasMoreMessages) return;
        isLoadingMore = true;

        var nextPage = currentPage - 1;
        if (nextPage < 1) {
            hasMoreMessages = false;
            isLoadingMore = false;
            return;
        }

        var messagesContainer = document.getElementById('chatMessages');
        var loadingDiv = document.createElement('div');
        loadingDiv.id = 'loading-more';
        loadingDiv.style.cssText = 'text-align:center;padding:10px;color:#94a3b8;font-size:12px;';
        loadingDiv.textContent = '加载更多...';
        messagesContainer.insertBefore(loadingDiv, messagesContainer.firstChild);

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_group_messages.php?group_id=' + groupId + '&page=' + nextPage, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                var loadingEl = document.getElementById('loading-more');
                if (loadingEl) loadingEl.remove();

                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = xhr.responseText;
                var paginationInfo = tempDiv.querySelector('#pagination-info');
                if (paginationInfo) {
                    currentPage = parseInt(paginationInfo.getAttribute('data-page'));
                    totalPages = parseInt(paginationInfo.getAttribute('data-total-pages'));
                    lastMessageId = paginationInfo.getAttribute('data-last-id') || '';
                    paginationInfo.remove();
                }

                var firstChild = messagesContainer.firstChild;
                while (tempDiv.firstChild) {
                    messagesContainer.insertBefore(tempDiv.firstChild, firstChild);
                }

                hasMoreMessages = (currentPage > 1);
            }
            isLoadingMore = false;
        };
        xhr.onerror = function() {
            var loadingEl = document.getElementById('loading-more');
            if (loadingEl) loadingEl.remove();
            isLoadingMore = false;
        };
        xhr.send();
    }

    function refreshCurrentPage(force) {
        var xhr = new XMLHttpRequest();
        var url = 'get_group_messages.php?group_id=' + groupId + '&page=0';
        if (lastMessageId && !force) {
            url += '&last_id=' + encodeURIComponent(lastMessageId);
        }
        xhr.open('GET', url, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                if (xhr.responseText === 'NOCHANGE') return;

                var messagesContainer = document.getElementById('chatMessages');
                var scrollBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop;

                messagesContainer.innerHTML = xhr.responseText;

                if (scrollBottom < 150) {
                    var st = document.getElementById('scrollTarget');
                    if (st) st.scrollIntoView();
                }

                var paginationInfo = messagesContainer.querySelector('#pagination-info');
                if (paginationInfo) {
                    currentPage = parseInt(paginationInfo.getAttribute('data-page'));
                    totalPages = parseInt(paginationInfo.getAttribute('data-total-pages'));
                    lastMessageId = paginationInfo.getAttribute('data-last-id') || '';
                    paginationInfo.remove();
                }
            }
        };
        xhr.send();
    }
    </script>
    <script>animateEntrance();</script>
</body>
</html>
