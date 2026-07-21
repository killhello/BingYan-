<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$currentUser = $_SESSION['username'];
$isAdminUser = isAdmin();
$friend = isset($_GET['friend']) ? $_GET['friend'] : '';

if (empty($friend)) {
    header('Location: friends.php');
    exit();
}

$dataDir = 'data';
$friendsFile = $dataDir . '/friends.json';
$usersDir = 'users';

function readJson($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function getUserById($username) {
    global $usersDir;
    $file = $usersDir . '/' . $username . '.json';
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

// 检查好友关系
$friends = readJson($friendsFile);
$isFriend = false;
$remark = $friend;
foreach ($friends as $f) {
    if ($f['user1'] === $currentUser && $f['user2'] === $friend) {
        $isFriend = true;
        if (!empty($f['remark1'])) $remark = $f['remark1'];
        break;
    } elseif ($f['user1'] === $friend && $f['user2'] === $currentUser) {
        $isFriend = true;
        if (!empty($f['remark2'])) $remark = $f['remark2'];
        break;
    }
}

if (!$isFriend) {
    header('Location: friends.php');
    exit();
}

$friendData = getUserById($friend);
$friendById = $friendData && isset($friendData['by_id']) ? $friendData['by_id'] : '';

// 生成会话ID
$users = [$currentUser, $friend];
sort($users);
$chatId = md5($users[0] . '_' . $users[1]);

// 读取消息
$chatDir = 'chatlogs/private/' . $chatId;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$messagesPerPage = 50;

$allMessages = [];
if (is_dir($chatDir)) {
    $chunks = glob($chatDir . '/chatlog_*.json');
    sort($chunks);
    foreach ($chunks as $chunkFile) {
        if (!file_exists($chunkFile)) continue;
        $fp = fopen($chunkFile, 'r');
        if (!$fp) continue;
        flock($fp, LOCK_SH);
        $json = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $msgs = json_decode($json, true);
        if (is_array($msgs)) $allMessages = array_merge($allMessages, $msgs);
    }
}

usort($allMessages, function($a, $b) { return $a['timestamp'] - $b['timestamp']; });
$totalMessages = count($allMessages);
$totalPages = ceil($totalMessages / $messagesPerPage);
$start = ($page - 1) * $messagesPerPage;
$pageMessages = array_slice($allMessages, $start, $messagesPerPage);
$username = $currentUser;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($remark); ?> - 私聊</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f2f5; height: 100vh; display: flex; flex-direction: column; }
        .chat-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); flex-shrink: 0; }
        .chat-header h1 { font-size: 18px; }
        .chat-header p { font-size: 12px; opacity: 0.9; }
        .header-actions a { color: white; text-decoration: none; margin-left: 15px; padding: 6px 12px; background: rgba(255,255,255,0.2); border-radius: 15px; font-size: 13px; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 20px; padding-bottom: 100px; }
        .message-item { display: flex; margin-bottom: 15px; max-width: 70%; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .message-left { margin-right: auto; }
        .message-right { margin-left: auto; flex-direction: row-reverse; }
        .message-right .message-content-wrap { align-items: flex-end; }
        .message-right .message-bubble { background: #22c55e; color: white; border-bottom-right-radius: 4px; }
        .message-ai .message-bubble { background: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; border-bottom-left-radius: 4px; }
        .message-ai .message-meta { color: #9333ea; font-weight: 500; }
        .ai-thinking { display: flex; align-items: center; gap: 8px; color: #9333ea; font-size: 13px; padding: 8px 15px; }
        .ai-thinking-dots span { display: inline-block; width: 6px; height: 6px; background: #9333ea; border-radius: 50%; animation: ai-dot 1.4s infinite ease-in-out both; margin: 0 2px; }
        .ai-thinking-dots span:nth-child(1) { animation-delay: -0.32s; }
        .ai-thinking-dots span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes ai-dot { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
        .avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px; margin: 0 10px; flex-shrink: 0; }
        .message-content-wrap { display: flex; flex-direction: column; }
        .message-meta { font-size: 12px; color: #718096; margin-bottom: 4px; padding: 0 5px; }
        .message-right .message-meta { text-align: right; }
        .message-bubble { padding: 10px 15px; border-radius: 10px; word-wrap: break-word; line-height: 1.5; font-size: 14px; min-height: 20px; background: white; border-bottom-left-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .message-bubble img.chat-image { max-width: 250px; max-height: 200px; border-radius: 6px; cursor: pointer; display: block; }
        .message-bubble img.chat-image:first-child { margin-top: 0; }
        .message-bubble img.chat-image:not(:first-child) { margin-top: 5px; }
        .chat-input-fixed { position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid #e0e0e0; padding: 10px 0; z-index: 100; }
        .chat-form { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .chat-input-container { display: flex; gap: 10px; align-items: flex-end; }
        #messageInput { flex: 1; padding: 12px; border: 2px solid #e0e0e0; border-radius: 25px; resize: none; font-family: inherit; font-size: 14px; min-height: 50px; max-height: 120px; }
        #messageInput:focus { outline: none; border-color: #667eea; }
        .send-btn { padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 25px; cursor: pointer; font-size: 14px; }
        .send-btn:hover { opacity: 0.9; }
        .send-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .status { position: fixed; top: 80px; right: 20px; padding: 10px 20px; border-radius: 8px; color: white; z-index: 1000; animation: slideIn 0.3s; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .status.success { background: #48bb78; }
        .status.error { background: #e53e3e; }
        .upload-btn { display: flex; align-items: center; justify-content: center; width: 50px; height: 50px; border: 1px solid #ddd; border-radius: 50%; cursor: pointer; font-size: 22px; color: #666; background: white; flex-shrink: 0; }
        .upload-btn:hover { border-color: #4299e1; color: #4299e1; }
        .upload-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        #imageInput { display: none; }
        .image-preview-container { display: none; padding: 10px 15px; background: #f8f9fa; border-top: 1px solid #eee; }
        .image-preview-container.active { display: block; }
        .image-preview-wrap { position: relative; display: inline-block; max-width: 200px; }
        .image-preview-wrap img { max-width: 200px; max-height: 150px; border-radius: 8px; border: 1px solid #ddd; }
        .image-preview-remove { position: absolute; top: -8px; right: -8px; width: 24px; height: 24px; border-radius: 50%; background: #dc3545; color: white; border: none; cursor: pointer; font-size: 14px; line-height: 24px; text-align: center; }
        .upload-progress { display: none; padding: 5px 15px; background: #f8f9fa; border-top: 1px solid #eee; }
        .upload-progress.active { display: block; }
        .upload-progress-bar { height: 4px; background: #e0e0e0; border-radius: 2px; overflow: hidden; }
        .upload-progress-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #4299e1, #667eea); border-radius: 2px; transition: width 0.3s; }
        .upload-progress-text { font-size: 12px; color: #666; margin-top: 4px; }
        .image-lightbox { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; justify-content: center; align-items: center; cursor: pointer; }
        .image-lightbox.active { display: flex; }
        .image-lightbox img { max-width: 90%; max-height: 90%; border-radius: 4px; }
        .image-lightbox-close { position: absolute; top: 20px; right: 30px; color: white; font-size: 36px; cursor: pointer; z-index: 10000; }
        @media (max-width: 768px) { .message-item { max-width: 90%; } }
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
            margin-top: 4px;
            text-align: right;
            padding: 0 5px;
            user-select: none;
        }
        .message-left .recall-btn {
            display: none;
            font-size: 11px;
            color: #6366f1;
            cursor: pointer;
            margin-top: 4px;
            text-align: left;
            padding: 0 5px;
            user-select: none;
        }
        .message-right:hover .recall-btn { display: block; }
        .message-left:hover .recall-btn { display: block; }
        .recall-btn.unrecall { color: #dc2626; }
        .recall-btn.unrecall:hover { color: #dc2626; }
        .message-right:hover .recall-btn.unrecall { display: block; }
        .message-left:hover .recall-btn.unrecall { display: block; }
.level-img {
    width: 60px;
    height: 48px;
    object-fit: cover;
    object-position: center top;
    vertical-align: middle;
    border-radius: 2px;
}
    </style>
</head>
<body>
    <?php include 'sidebar_component.php'; ?>
    <div class="chat-header">
        <div>
            <h1><?php echo htmlspecialchars($remark); ?></h1>
            <p>BY号：<?php echo htmlspecialchars($friendById); ?></p>
        </div>
        <div class="header-actions">
            <a href="friends.php?action=delete&friend=<?php echo urlencode($friend); ?>" onclick="return confirm('确定要删除好友吗？')">删除好友</a>
            <a href="friends.php">好友列表</a>
            <a href="chat.php">公共聊天室</a>
        </div>
    </div>

    <div class="chat-messages" id="chatMessages">
        <?php if (empty($pageMessages)): ?>
            <div style="text-align:center;padding:20px;color:#718096;">暂无消息，发送第一条消息吧！</div>
        <?php else: ?>
            <?php foreach ($pageMessages as $msg): ?>
                <?php
                if (!isset($msg['username']) || !isset($msg['timestamp'])) continue;
                $isSelf = ($msg['username'] === $currentUser);
                $isAi = ($msg['username'] === 'AI助手');
                if ($isAi) {
                    $messageClass = 'message-ai';
                    $avatarBg = '#9333ea';
                } else {
                    $messageClass = $isSelf ? 'message-right' : 'message-left';
                    $avatarBg = $isSelf ? '#4299e1' : '#48bb78';
                }
                $time = date('H:i', $msg['timestamp']);
                ?>
                <div class="message-item <?php echo $messageClass; ?>">
                    <?php if ($isAi): ?>
                        <div class="avatar" style="background-color:#9333ea;">AI</div>
                    <?php else: ?>
                        <?php $firstLetter = strtoupper(substr(htmlspecialchars($msg['username']), 0, 1)); ?>
                        <img src="avatar.php?name=<?php echo urlencode($msg['username']); ?>" class="avatar" style="object-fit:cover;" onerror="this.onerror=null;this.outerHTML='<div class=\'avatar\' style=\'background-color:<?php echo $avatarBg; ?>\'><?php echo $firstLetter; ?></div>'">
                    <?php endif; ?>
                    <div class="message-content-wrap">
                        <div class="message-meta"><?php echo formatUserDisplay($msg['username']); ?> <?php echo $time; ?><?php if (($isSelf || $isAdminUser) && !empty($msg['recalled'])): ?> <span class="recalled-tag">已撤回</span><?php endif; ?></div>
                        <div class="message-bubble">
                            <?php if (!empty($msg['recalled'])): ?>
                                <em class="recalled-msg"><?php echo (!empty($msg['recalled_by']) && isAdminUser($msg['recalled_by'])) ? '已被管理员撤回' : '该消息已撤回'; ?></em>
                            <?php else: ?>
                                <?php if (!empty($msg['message'])) echo htmlspecialchars($msg['message']); ?>
                                <?php if (!empty($msg['image_url'])) echo '<img class="chat-image" src="' . htmlspecialchars($msg['image_url']) . '" alt="图片" loading="lazy">'; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (($isSelf || $isAdminUser) && empty($msg['recalled']) && ($isAdminUser || time() - $msg['timestamp'] <= 120)): ?>
                            <?php $mid = md5($msg['username'] . '|' . $msg['timestamp'] . '|' . ($msg['message'] ?? '') . '|' . ($msg['image_url'] ?? '')); ?>
                            <div class="recall-btn" data-mid="<?php echo $mid; ?>" data-username="<?php echo htmlspecialchars($msg['username']); ?>" data-timestamp="<?php echo $msg['timestamp']; ?>" data-message="<?php echo htmlspecialchars($msg['message'] ?? ''); ?>" data-image="<?php echo htmlspecialchars($msg['image_url'] ?? ''); ?>" onclick="recallMessage(this)">撤回</div>
                        <?php endif; ?>
                        <?php if ($isAdminUser && !empty($msg['recalled'])): ?>
                            <?php $mid = md5($msg['username'] . '|' . $msg['timestamp'] . '|' . ($msg['message'] ?? '') . '|' . ($msg['image_url'] ?? '')); ?>
                            <div class="recall-btn unrecall" data-mid="<?php echo $mid; ?>" data-username="<?php echo htmlspecialchars($msg['username']); ?>" data-timestamp="<?php echo $msg['timestamp']; ?>" data-message="<?php echo htmlspecialchars($msg['message'] ?? ''); ?>" data-image="<?php echo htmlspecialchars($msg['image_url'] ?? ''); ?>" onclick="unrecallMessage(this)">取消撤回</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div id="pagination-info" data-page="<?php echo $page; ?>" data-total-pages="<?php echo $totalPages; ?>" data-total="<?php echo $totalMessages; ?>" style="display:none;"></div>
    </div>

    <div class="chat-input-fixed">
        <div class="image-preview-container" id="imagePreviewContainer">
            <div class="image-preview-wrap">
                <img id="imagePreview" src="" alt="预览">
                <button type="button" class="image-preview-remove" id="removeImage">&times;</button>
            </div>
        </div>
        <div class="upload-progress" id="uploadProgress">
            <div class="upload-progress-bar"><div class="upload-progress-fill" id="uploadProgressFill"></div></div>
            <div class="upload-progress-text" id="uploadProgressText">上传中 0%</div>
        </div>
        <form class="chat-form" id="chatForm">
            <div class="chat-input-container">
                <label class="upload-btn" id="uploadBtn" title="上传图片">&#128247;<input type="file" id="imageInput" accept="image/jpeg,image/png"></label>
                <textarea id="messageInput" name="message" placeholder="请输入消息..."></textarea>
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
    var pendingImageUrl = null;
    var currentPage = <?php echo $page; ?>;
    var totalPages = <?php echo $totalPages; ?>;
    var isLoadingMore = false;
    var hasMoreMessages = true;
    var friendName = '<?php echo addslashes($friend); ?>';
    var chatId = '<?php echo $chatId; ?>';
    var isAdminUser = <?php echo $isAdminUser ? 'true' : 'false'; ?>;
    var lastMessageId = '';

    function showStatus(msg, type) {
        var c = document.getElementById('statusContainer');
        var el = document.createElement('div');
        el.className = 'status ' + type;
        el.textContent = msg;
        c.appendChild(el);
        setTimeout(function() { el.style.opacity = '0'; el.style.transition = 'opacity 0.5s'; setTimeout(function() { el.remove(); }, 500); }, 2000);
    }

    // 图片上传
    var imageInput = document.getElementById('imageInput');
    imageInput.addEventListener('change', function() {
        var file = this.files[0];
        if (!file) return;
        if (!file.type.match(/^image\/(jpeg|png)$/)) { showStatus('仅支持 JPG/PNG', 'error'); this.value = ''; return; }
        if (file.size > 5 * 1024 * 1024) { showStatus('图片不能超过5MB', 'error'); this.value = ''; return; }
        var reader = new FileReader();
        reader.onload = function(e) { document.getElementById('imagePreview').src = e.target.result; document.getElementById('imagePreviewContainer').classList.add('active'); };
        reader.readAsDataURL(file);
        uploadImage(file);
    });

    document.getElementById('removeImage').addEventListener('click', function() {
        pendingImageUrl = null;
        document.getElementById('imagePreviewContainer').classList.remove('active');
        document.getElementById('imagePreview').src = '';
        imageInput.value = '';
        document.getElementById('uploadProgress').classList.remove('active');
    });

    function uploadImage(file) {
        var btn = document.getElementById('uploadBtn');
        btn.disabled = true;
        var prog = document.getElementById('uploadProgress');
        var fill = document.getElementById('uploadProgressFill');
        var text = document.getElementById('uploadProgressText');
        prog.classList.add('active');
        fill.style.width = '0%';
        text.textContent = '上传中 0%';
        var fd = new FormData();
        fd.append('image', file);
        var xhr = new XMLHttpRequest();
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) { var p = Math.round((e.loaded / e.total) * 100); fill.style.width = p + '%'; text.textContent = '上传中 ' + p + '%'; }
        });
        xhr.onload = function() {
            btn.disabled = false;
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    var url = '';
                    if (data.status === 'success' && data.data && data.data.public_url) url = data.data.public_url;
                    else if (data.status === true && data.data && data.data.links) url = data.data.links.url;
                    if (url) { pendingImageUrl = url; text.textContent = '上传成功'; fill.style.width = '100%'; setTimeout(function() { prog.classList.remove('active'); }, 1000); showStatus('图片已就绪，点击发送', 'success'); }
                    else { showStatus('上传失败: ' + (data.message || '未知'), 'error'); resetUpload(); }
                } catch(e) { showStatus('上传失败', 'error'); resetUpload(); }
            } else { showStatus('上传失败', 'error'); resetUpload(); }
        };
        xhr.onerror = function() { btn.disabled = false; showStatus('网络错误', 'error'); resetUpload(); };
        xhr.open('POST', 'upload_image.php', true);
        xhr.send(fd);
    }

    function resetUpload() {
        pendingImageUrl = null;
        document.getElementById('imagePreviewContainer').classList.remove('active');
        document.getElementById('imagePreview').src = '';
        imageInput.value = '';
        document.getElementById('uploadProgress').classList.remove('active');
    }

    // 灯箱
    document.getElementById('imageLightbox').addEventListener('click', function(e) { if (e.target === this || e.target.id === 'lightboxClose') this.classList.remove('active'); });
    document.addEventListener('click', function(e) { if (e.target.classList.contains('chat-image')) { document.getElementById('lightboxImage').src = e.target.src; document.getElementById('imageLightbox').classList.add('active'); } });

    // 发送消息
    document.getElementById('chatForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var input = document.getElementById('messageInput');
        var value = input.value.trim();
        if (!value && !pendingImageUrl) { showStatus('请输入内容或选择图片', 'error'); return; }
        if (value && value.length > 100) { showStatus('消息不能超过100字', 'error'); return; }
        var btn = document.getElementById('sendBtn');
        btn.disabled = true;

        var isAiCommand = value.indexOf('@ai') === 0;
        var aiPrompt = '';
        if (isAiCommand) {
            aiPrompt = value.substring(3).trim();
            if (!aiPrompt && !pendingImageUrl) {
                showStatus('请输入AI提示词！', 'error');
                btn.disabled = false;
                input.focus();
                return;
            }
        }

        var fd = new FormData();
        fd.append('message', value);
        fd.append('friend', friendName);
        if (pendingImageUrl) fd.append('image_url', pendingImageUrl);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'send_friend_message.php', true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try { var data = JSON.parse(xhr.responseText); if (data.success) { input.value = ''; resetUpload(); refreshCurrentPage(); showStatus('发送成功', 'success'); if (isAiCommand) callAiAndReply(aiPrompt); } else showStatus('发送失败: ' + data.error, 'error'); }
                catch(e) { showStatus('发送失败', 'error'); }
            } else showStatus('发送失败', 'error');
            if (!isAiCommand) { btn.disabled = false; input.focus(); }
        };
        xhr.onerror = function() { showStatus('网络错误', 'error'); btn.disabled = false; };
        xhr.send(fd);
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
                        var aiForm = new FormData();
                        aiForm.append('message', aiData.content);
                        aiForm.append('ai_sender', 'AI助手');
                        aiForm.append('friend', friendName);

                        var saveXhr = new XMLHttpRequest();
                        saveXhr.open('POST', 'send_friend_message.php', true);
                        saveXhr.onload = function() {
                            refreshCurrentPage();
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

    function recallMessage(el) {
        var username = el.getAttribute('data-username');
        var timestamp = el.getAttribute('data-timestamp');
        var message = el.getAttribute('data-message');
        var image = el.getAttribute('data-image');

        var formData = new FormData();
        formData.append('username', username);
        formData.append('timestamp', timestamp);
        formData.append('message', message);
        formData.append('image_url', image);
        formData.append('private_id', chatId);

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
        xhr.onerror = function() { showStatus('网络错误', 'error'); };
        xhr.send(formData);
    }

    function unrecallMessage(el) {
        var username = el.getAttribute('data-username');
        var timestamp = el.getAttribute('data-timestamp');
        var message = el.getAttribute('data-message');
        var image = el.getAttribute('data-image');

        var formData = new FormData();
        formData.append('username', username);
        formData.append('timestamp', timestamp);
        formData.append('message', message);
        formData.append('image_url', image);
        formData.append('private_id', chatId);
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
        xhr.onerror = function() { showStatus('网络错误', 'error'); };
        xhr.send(formData);
    }

    window.onload = function() {
        var mc = document.getElementById('chatMessages');
        mc.scrollTop = mc.scrollHeight;
        document.getElementById('messageInput').focus();
        mc.addEventListener('scroll', function() { if (mc.scrollTop < 50 && !isLoadingMore && hasMoreMessages) loadMoreMessages(); });
        setInterval(refreshCurrentPage, 3000);
    };

    function refreshCurrentPage(force) {
        var xhr = new XMLHttpRequest();
        var url = 'get_friend_messages.php?friend=' + encodeURIComponent(friendName) + '&page=' + currentPage;
        if (lastMessageId && !force) {
            url += '&last_id=' + encodeURIComponent(lastMessageId);
        }
        xhr.open('GET', url, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                if (xhr.responseText === 'NOCHANGE') return;
                var mc = document.getElementById('chatMessages');
                var sb = mc.scrollHeight - mc.scrollTop;
                mc.innerHTML = xhr.responseText;
                if (sb < 150) { var st = document.getElementById('scrollTarget'); if (st) st.scrollIntoView(); }
                var pi = mc.querySelector('#pagination-info');
                if (pi) { currentPage = parseInt(pi.getAttribute('data-page')); totalPages = parseInt(pi.getAttribute('data-total-pages')); lastMessageId = pi.getAttribute('data-last-id') || ''; pi.remove(); }
                hasMoreMessages = (currentPage < totalPages);
            }
        };
        xhr.send();
    }

    function loadMoreMessages() {
        if (isLoadingMore || !hasMoreMessages) return;
        isLoadingMore = true;
        var np = currentPage + 1;
        var mc = document.getElementById('chatMessages');
        var ld = document.createElement('div');
        ld.id = 'loading-more';
        ld.style.cssText = 'text-align:center;padding:10px;color:#718096;font-size:12px;';
        ld.textContent = '加载更多...';
        mc.insertBefore(ld, mc.firstChild);
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_friend_messages.php?friend=' + encodeURIComponent(friendName) + '&page=' + np, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                var le = document.getElementById('loading-more'); if (le) le.remove();
                var td = document.createElement('div'); td.innerHTML = xhr.responseText;
                var pi = td.querySelector('#pagination-info');
                if (pi) { currentPage = parseInt(pi.getAttribute('data-page')); totalPages = parseInt(pi.getAttribute('data-total-pages')); pi.remove(); }
                var fc = mc.firstChild;
                while (td.firstChild) mc.insertBefore(td.firstChild, fc);
                hasMoreMessages = (currentPage < totalPages);
            }
            isLoadingMore = false;
        };
        xhr.onerror = function() { var le = document.getElementById('loading-more'); if (le) le.remove(); isLoadingMore = false; };
        xhr.send();
    }
    </script>
</body>
</html>
