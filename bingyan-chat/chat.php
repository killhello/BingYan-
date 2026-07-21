<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
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
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BingYan PHP 5.6 聊天室</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Microsoft Yahei", sans-serif;
}
body {
    background: rgb(34,193,195);
    background: linear-gradient(0deg, rgba(34,193,195,1) 0%, rgba(253,187,45,1) 100%);
    background-attachment: fixed;
    min-height: 100vh;
    padding-bottom: 100px;
    max-width: 100%;
    overflow-x: hidden;
}
.chat-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px 15px;
}
.chat-header {
    text-align: center;
    margin-bottom: 24px;
    padding: 24px 20px 16px;
    background: rgba(255,255,255,0.6);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.8);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.06);
    position: sticky;
    top: 0;
    z-index: 10;
}
.chat-header h1 {
    color: #1a1a2e;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 1px;
}
.chat-header p {
    color: #64748b;
    font-size: 12px;
    margin-top: 4px;
}
.rainbow-line {
    height: 2px;
    width: 60%;
    margin: 12px auto 0;
    background: linear-gradient(90deg, #ff6b6b, #ffd93d, #6bcb77, #4d96ff, #9b59b6, #ff6b6b);
    background-size: 200% 100%;
    animation: rainbow-flow 3s linear infinite;
    border-radius: 2px;
}
@keyframes rainbow-flow {
    0% { background-position: 0 0; }
    100% { background-position: 200% 0; }
}
.user-info {
    font-size: 13px;
    color: #64748b;
    margin-top: 10px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
}
.logout-btn {
    background: rgba(255,255,255,0.7);
    color: #475569;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 6px 14px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
    transition: all 0.3s;
}
.logout-btn:hover {
    background: rgba(239,68,68,0.1);
    border-color: rgba(239,68,68,0.2);
    color: #dc2626;
}

.chat-messages {
    min-height: calc(100vh - 200px);
    padding: 15px 0;
}
.message-item {
    display: flex;
    margin-bottom: 20px;
    max-width: 80%;
}
.message-other { flex-direction: row; margin-right: auto; }
.message-self { flex-direction: row-reverse; margin-left: auto; }
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
    flex-shrink: 0;
    position: relative;
}
.avatar::before {
    content: '';
    position: absolute;
    inset: -2px;
    border-radius: 50%;
    background: linear-gradient(90deg, #ff6b6b, #ffd93d, #6bcb77, #4d96ff, #9b59b6, #ff6b6b);
    background-size: 200% 100%;
    animation: rainbow-flow 3s linear infinite;
    z-index: -1;
}
.avatar::after {
    content: '';
    position: absolute;
    inset: 1px;
    border-radius: 50%;
    background: inherit;
    z-index: -1;
}
.message-content-wrap { margin: 0 10px; max-width: calc(100% - 60px); }
.message-meta { font-size: 11px; margin-bottom: 4px; font-weight: 500; }
.message-other .message-meta { color: #000; }
.message-self .message-meta { color: #000; text-align: right; }
.message-bubble {
    padding: 10px 16px;
    border-radius: 14px;
    word-wrap: break-word;
    line-height: 1.5;
    font-size: 14px;
    min-height: 20px;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.message-other .message-bubble {
    background: rgba(255,255,255,0.7);
    border: 1px solid rgba(255,255,255,0.9);
    color: #000;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.message-self .message-bubble {
    background: rgba(34,197,94,0.15);
    border: 1px solid rgba(34,197,94,0.2);
    color: #000;
    border-bottom-right-radius: 4px;
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
.empty-records {
    text-align: center;
    color: #94a3b8;
    padding: 60px 0;
    font-size: 14px;
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
#messageInput:focus { border-color: rgba(34,197,94,0.3); background: white; }
.send-btn {
    padding: 0 24px;
    height: 48px;
    background: rgba(34,197,94,0.12);
    color: #16a34a;
    border: 1px solid rgba(34,197,94,0.2);
    border-radius: 14px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s;
    backdrop-filter: blur(8px);
}
.send-btn:hover {
    background: rgba(34,197,94,0.2);
    border-color: rgba(34,197,94,0.3);
    transform: scale(1.02);
}
.send-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
    transform: none;
}
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
/* 公告弹窗 - 玻璃形态 */
.announcement-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.3);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}
.announcement-overlay.active {
    display: flex;
}
.announcement-modal {
    background: rgba(255,255,255,0.75);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255,255,255,0.9);
    border-radius: 20px;
    max-width: 520px;
    width: 90%;
    max-height: 80vh;
    box-shadow: 0 24px 80px rgba(0,0,0,0.15);
    overflow: hidden;
    animation: announce-in 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes announce-in {
    from { transform: scale(0.92) translateY(20px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}
.announcement-header {
    background: rgba(255,255,255,0.5);
    border-bottom: 1px solid rgba(0,0,0,0.06);
    color: #1a1a2e;
    padding: 20px 24px;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}
.announcement-header .icon {
    font-size: 22px;
}
.announcement-body {
    padding: 24px;
    max-height: 50vh;
    overflow-y: auto;
    line-height: 1.8;
    font-size: 15px;
    color: #334155;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.announcement-footer {
    padding: 16px 24px;
    border-top: 1px solid rgba(0,0,0,0.06);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}
.announcement-btn {
    padding: 10px 22px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 500;
}
.announcement-btn-primary {
    background: rgba(102,126,234,0.12);
    color: #6366f1;
    border: 1px solid rgba(102,126,234,0.2);
    backdrop-filter: blur(8px);
}
.announcement-btn-primary:hover {
    background: rgba(102,126,234,0.2);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(102,126,234,0.15);
}
.announcement-btn-ghost {
    background: rgba(0,0,0,0.04);
    color: #64748b;
    border: 1px solid rgba(0,0,0,0.06);
}
.announcement-btn-ghost:hover {
    background: rgba(0,0,0,0.08);
    color: #1e293b;
}
.announcement-footer .hint {
    font-size: 12px;
    color: #94a3b8;
    margin-right: auto;
    align-self: center;
}
/* 定制滚动条 */
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.12); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.2); }

/* 图片上传 - 玻璃形态 */
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
.upload-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}
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
    background: linear-gradient(90deg, #22c55e, #16a34a);
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
.message-bubble img.chat-image:hover {
    opacity: 0.9;
}
.message-bubble img.chat-image:first-child {
    margin-top: 0;
}
.message-bubble img.chat-image:not(:first-child) {
    margin-top: 5px;
}
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
.image-lightbox.active {
    display: flex;
}
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
/* 公告编辑面板 - 玻璃形态 */
.announcement-editor {
    display: none;
    max-width: 800px;
    margin: 0 auto 15px;
    padding: 20px;
    background: rgba(255,255,255,0.6);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.8);
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.06);
}
.announcement-editor.active { display: block; }
.announcement-editor .editor-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
}
.announcement-editor .editor-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #1a1a2e;
}
.announcement-editor .editor-header .close-btn {
    background: none;
    border: none;
    font-size: 20px;
    color: #94a3b8;
    cursor: pointer;
    padding: 0 5px;
    transition: all 0.3s;
}
.announcement-editor .editor-header .close-btn:hover { color: #1e293b; transform: rotate(90deg); }
.announcement-editor textarea {
    width: 100%;
    min-height: 120px;
    padding: 14px;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.6;
    resize: vertical;
    outline: none;
    background: rgba(255,255,255,0.7);
    color: #1e293b;
    transition: all 0.3s;
}
.announcement-editor textarea::placeholder { color: #94a3b8; }
.announcement-editor textarea:focus {
    border-color: rgba(102,126,234,0.3);
}
.announcement-editor .editor-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 12px;
}
.announcement-editor .editor-hint {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 8px;
}
.announcement-editor .current-preview {
    background: rgba(255,255,255,0.5);
    border: 1px solid rgba(0,0,0,0.06);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    white-space: pre-wrap;
    line-height: 1.6;
    font-size: 14px;
    color: #475569;
    max-height: 100px;
    overflow-y: auto;
}
.announcement-editor .current-preview:empty { display: none; }
.editor-status {
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 12px;
    display: none;
    backdrop-filter: blur(8px);
}
.editor-status.success {
    display: block;
    background: rgba(34,197,94,0.12);
    color: #16a34a;
    border: 1px solid rgba(34,197,94,0.15);
}
.editor-status.error {
    display: block;
    background: rgba(239,68,68,0.1);
    color: #dc2626;
    border: 1px solid rgba(239,68,68,0.15);
}
.editor-status.success.active,
.editor-status.error.active {
    display: block;
}
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
.message-self .recall-btn {
    display: none;
    font-size: 11px;
    color: #6366f1;
    cursor: pointer;
    text-align: right;
    padding: 2px 5px 0;
    transition: color 0.2s;
}
.message-self .recall-btn:hover { color: #dc2626; }
.message-self:hover .recall-btn { display: block; }
.message-item .recall-btn.unrecall {
    display: none;
    font-size: 11px;
    color: #f59e0b;
    cursor: pointer;
    text-align: right;
    padding: 2px 5px 0;
    transition: color 0.2s;
}
.message-item .recall-btn.unrecall:hover { color: #dc2626; }
.message-item:hover .recall-btn.unrecall { display: block; }

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
.message-other.animate-in,
.sidebar-toggle.animate-in {
    animation: slideInLeft 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
}
.message-self.animate-in,
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
</style>
</head>
<body>
<?php include 'sidebar_component.php'; ?>

<?php
$currentAnnouncement = '';
$announcementFile = 'data/announcement.json';
if (file_exists($announcementFile)) {
    $ad = json_decode(file_get_contents($announcementFile), true);
    if (is_array($ad)) {
        $currentAnnouncement = $ad['content'] ?? '';
    }
}
?>

<div class="chat-container">
    <div class="chat-header">
        <h2>BingYan PHP 5.6 聊天室</h2>
        <div class="rainbow-line"></div>
    </div>

    <div class="chat-messages" id="chatMessages">
        <?php
        $chatLogFile = 'chatlogs/chatlog.json';
        $messages = [];
        if (file_exists($chatLogFile)) {
            $json = file_get_contents($chatLogFile);
            $messages = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $messages = [];
            }
        }

        if (!empty($messages) && is_array($messages)) {
            foreach ($messages as $msg) {
                $isSelf = ($msg['username'] === $username);
                $isAi = ($msg['username'] === 'AI助手');
                if ($isAi) {
                    $avatarBg = '#9333ea';
                    $messageClass = 'message-ai';
                } else {
                    $avatarColor = substr(md5($msg['username']), 0, 6);
                    $avatarBg = '#' . $avatarColor;
                    $messageClass = $isSelf ? 'message-self' : 'message-other';
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
                if (($isSelf || $isAdminUser) && !empty($msg['recalled'])) {
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
                if (($isSelf || $isAdminUser) && empty($msg['recalled']) && ($isAdminUser || time() - $msg['timestamp'] <= 120)) {
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
        } else {
            echo '<div class="empty-records">暂无聊天记录!</div>';
        }
        ?>
        <span id="scrollTarget"></span>
    </div>
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
                <input type="file" id="imageInput" accept="image/*">
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

<?php if ($isAdminUser): ?>
<div class="announcement-editor" id="announcementEditor">
    <div class="editor-header">
        <h3>&#128240; 公告管理</h3>
        <button type="button" class="close-btn" onclick="toggleAnnouncementEditor()">&times;</button>
    </div>
    <div class="editor-status" id="editorStatus"></div>
    <div class="current-preview" id="currentPreview"><?php echo htmlspecialchars($currentAnnouncement); ?></div>
    <textarea id="announcementEditorContent" placeholder="输入公告内容（留空则不显示公告）"><?php echo htmlspecialchars($currentAnnouncement); ?></textarea>
    <div class="editor-actions">
        <span class="editor-hint">留空则不显示公告</span>
        <button class="announcement-btn announcement-btn-ghost" onclick="toggleAnnouncementEditor()">取消</button>
        <button class="announcement-btn announcement-btn-primary" onclick="saveAnnouncement(this)">保存公告</button>
    </div>
</div>
<?php endif; ?>

<div id="statusContainer"></div>

<div class="image-lightbox" id="imageLightbox">
    <span class="image-lightbox-close" id="lightboxClose">&times;</span>
    <img id="lightboxImage" src="" alt="大图">
</div>

<div class="announcement-overlay" id="announcementOverlay">
    <div class="announcement-modal">
        <div class="announcement-header">
            <span class="icon">&#128240;</span>
            <span>系统公告</span>
        </div>
        <div class="announcement-body" id="announcementBody"></div>
        <div class="announcement-footer">
            <span class="hint" id="announcementTime"></span>
            <?php if ($isAdminUser): ?>
            <button class="announcement-btn announcement-btn-ghost" id="announcementEdit" onclick="closeAnnouncementPopup();toggleAnnouncementEditor();">编辑公告</button>
            <?php endif; ?>
            <button class="announcement-btn announcement-btn-ghost" id="announcementDismissDay">今日不再提醒</button>
            <button class="announcement-btn announcement-btn-primary" id="announcementConfirm">我知道了</button>
        </div>
    </div>
</div>

<script>
var username = <?php echo json_encode($username); ?>;
<?php $curLevelTitle = getUserLevelAndTitle($username); ?>
var userLevel = <?php echo json_encode($curLevelTitle['level']); ?>;
var userTitle = <?php echo json_encode($curLevelTitle['title']); ?>;
let refreshTimer;
let pendingImageUrl = null;
let currentPage = 1;
let totalPages = 1;
let isLoadingMore = false;
let hasMoreMessages = true;
let lastMessageId = '';

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
    var userMessage = value;
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
    appendMessage(username, sentMessage, sentImage);

    var formData = new FormData();
    formData.append('message', userMessage);
    if (sentImage) {
        formData.append('image_url', sentImage);
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'send_message.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    showStatus('发送成功', 'success');
                    // 立即从服务器刷新以获取精确的时间戳和顺序
                    refreshCurrentPage();

                    if (isAiCommand) {
                        callAiAndReply(aiPrompt);
                    }
                } else {
                    removeLastMessage(username, sentMessage, sentImage);
                    showStatus('发送失败: ' + data.error, 'error');
                }
            } catch(e) {
                removeLastMessage(username, sentMessage, sentImage);
                showStatus('发送失败', 'error');
            }
        } else {
            removeLastMessage(username, sentMessage, sentImage);
            showStatus('发送失败', 'error');
        }
        if (!isAiCommand) {
            sendBtn.disabled = false;
            input.focus();
        }
    };
    xhr.onerror = function() {
        removeLastMessage(username, sentMessage, sentImage);
        showStatus('网络错误', 'error');
        sendBtn.disabled = false;
    };
    xhr.send(formData);
});

var messageInput = document.getElementById('messageInput');
if (messageInput) {
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('chatForm').dispatchEvent(new Event('submit'));
        }
    });
}

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

                    var savedAiContent = aiData.content;
                    var saveXhr = new XMLHttpRequest();
                    saveXhr.open('POST', 'send_message.php', true);
                    saveXhr.onload = function() {
                        if (saveXhr.status === 200) {
                            try {
                                var sd = JSON.parse(saveXhr.responseText);
                                if (sd.success) {
                                    refreshCurrentPage();
                                } else {
                                    showStatus('AI回复保存失败', 'error');
                                }
                            } catch(e) {
                                showStatus('AI回复保存失败', 'error');
                            }
                        } else {
                            showStatus('AI回复保存失败', 'error');
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

function appendMessage(username, message, imageUrl) {
    var container = document.getElementById('chatMessages');
    var now = new Date();
    var time = now.getFullYear() + '-' +
        ('0' + (now.getMonth() + 1)).slice(-2) + '-' +
        ('0' + now.getDate()).slice(-2) + ' ' +
        ('0' + now.getHours()).slice(-2) + ':' +
        ('0' + now.getMinutes()).slice(-2);
    var firstLetter = username.charAt(0).toUpperCase();
    var isAi = (username === 'AI助手');
    var msgClass = isAi ? 'message-ai' : 'message-self';

    var div = document.createElement('div');
    div.className = 'message-item ' + msgClass;

    var avatarHtml;
    if (isAi) {
        avatarHtml = '<div class="avatar" style="background-color:#9333ea;">AI</div>';
    } else {
        var avatarColor = '#' + simpleHash(username).substring(0, 6);
        avatarHtml = '<div class="avatar" style="background-color:' + avatarColor + ';">' + firstLetter + '</div>';
    }

    var emptyEl = container.querySelector('.empty-records');
    if (emptyEl) emptyEl.remove();

    div.innerHTML = avatarHtml +
        '<div class="message-content-wrap">' +
        '<div class="message-meta">' + escapeHtml(username) + ' <span class="user-level-badge">' + userLevel + '</span>' + (userTitle ? ' <span class="user-title-badge">[' + escapeHtml(userTitle) + ']</span>' : '') + ' ' + time + '</div>' +
        '<div class="message-bubble">' +
        (message ? formatMessage(message) : '') +
        (imageUrl ? '<img class="chat-image" src="' + escapeHtml(imageUrl) + '" alt="图片" loading="lazy">' : '') +
        '</div></div>';
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function removeLastMessage(username, message, imageUrl) {
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

function simpleHash(str) {
    var hash = 0;
    for (var i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i);
        hash |= 0;
    }
    return Math.abs(hash).toString(16);
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
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

    var formData = new FormData();
    formData.append('username', username);
    formData.append('timestamp', timestamp);
    formData.append('message', message);
    formData.append('image_url', image);

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

    var formData = new FormData();
    formData.append('username', username);
    formData.append('timestamp', timestamp);
    formData.append('message', message);
    formData.append('image_url', image);
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

function showAnnouncement() {
    var today = new Date().toISOString().split('T')[0];
    var dismissed = localStorage.getItem('announcement_dismissed');
    if (dismissed === today) return;

    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_announcement.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success && data.content) {
                    document.getElementById('announcementBody').textContent = data.content;
                    if (data.updated_at) {
                        var d = new Date(data.updated_at * 1000);
                        document.getElementById('announcementTime').textContent = '发布于 ' + d.toLocaleDateString('zh-CN');
                    }
                    document.getElementById('announcementOverlay').classList.add('active');
                }
            } catch(e) {}
        }
    };
    xhr.send();
}

document.getElementById('announcementConfirm').addEventListener('click', function() {
    document.getElementById('announcementOverlay').classList.remove('active');
});

document.getElementById('announcementDismissDay').addEventListener('click', function() {
    var today = new Date().toISOString().split('T')[0];
    localStorage.setItem('announcement_dismissed', today);
    document.getElementById('announcementOverlay').classList.remove('active');
});

function closeAnnouncementPopup() {
    document.getElementById('announcementOverlay').classList.remove('active');
}

function toggleAnnouncementEditor() {
    var editor = document.getElementById('announcementEditor');
    if (!editor) return;
    editor.classList.toggle('active');
}

function saveAnnouncement(btn) {
    btn = btn || document.querySelector('.announcement-editor .announcement-btn-primary');
    btn.disabled = true;
    btn.textContent = '保存中...';

    var content = document.getElementById('announcementEditorContent').value;
    var formData = new FormData();
    formData.append('content', content);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'save_announcement.php', true);
    xhr.onload = function() {
        btn.disabled = false;
        btn.textContent = '保存公告';
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    document.getElementById('currentPreview').textContent = content;
                    document.getElementById('editorStatus').className = 'editor-status success';
                    document.getElementById('editorStatus').textContent = '保存成功！';
                    setTimeout(function() {
                        document.getElementById('editorStatus').style.opacity = '0';
                        setTimeout(function() {
                            document.getElementById('editorStatus').className = 'editor-status';
                            document.getElementById('editorStatus').style.opacity = '1';
                        }, 500);
                    }, 2000);
                } else {
                    document.getElementById('editorStatus').className = 'editor-status error';
                    document.getElementById('editorStatus').textContent = '保存失败: ' + (data.error || '未知错误');
                }
            } catch(e) {
                document.getElementById('editorStatus').className = 'editor-status error';
                document.getElementById('editorStatus').textContent = '保存失败: 解析错误';
            }
        } else {
            document.getElementById('editorStatus').className = 'editor-status error';
            document.getElementById('editorStatus').textContent = '保存失败: 网络错误';
        }
    };
    xhr.onerror = function() {
        btn.disabled = false;
        btn.textContent = '保存公告';
        document.getElementById('editorStatus').className = 'editor-status error';
        document.getElementById('editorStatus').textContent = '保存失败: 网络错误';
    };
    xhr.send(formData);
}

window.onload = function() {
    var messagesContainer = document.getElementById('chatMessages');

    document.getElementById('messageInput').focus();

    loadMessages(0, true, function() {
        animateEntrance();
    });

    messagesContainer.addEventListener('scroll', function() {
        if (messagesContainer.scrollTop < 50 && !isLoadingMore && hasMoreMessages) {
            loadMoreMessages();
        }
    });

    clearInterval(refreshTimer);
    refreshTimer = setInterval(function() {
        refreshCurrentPage();
    }, 3000);

    showAnnouncement();
};

function loadMessages(page, scrollToBottom, callback) {
    if (page === undefined || page === null) page = 1;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_messages.php?page=' + page, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            var messagesContainer = document.getElementById('chatMessages');
            var oldScrollHeight = messagesContainer.scrollHeight;

            if (scrollToBottom) {
                messagesContainer.innerHTML = xhr.responseText;
                var st = document.getElementById('scrollTarget');
                if (st) st.scrollIntoView();
            } else {
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
                var newScrollHeight = messagesContainer.scrollHeight;
                messagesContainer.scrollTop = newScrollHeight - oldScrollHeight + 50;
            }

            var paginationInfo = messagesContainer.querySelector('#pagination-info');
            if (paginationInfo) {
                currentPage = parseInt(paginationInfo.getAttribute('data-page'));
                totalPages = parseInt(paginationInfo.getAttribute('data-total-pages'));
                lastMessageId = paginationInfo.getAttribute('data-last-id') || '';
                paginationInfo.remove();
            }

            hasMoreMessages = (currentPage > 1);
        }
        isLoadingMore = false;
        if (typeof callback === 'function') callback();
    };
    xhr.onerror = function() {
        isLoadingMore = false;
        if (typeof callback === 'function') callback();
    };
    xhr.send();
}

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
    xhr.open('GET', 'get_messages.php?page=' + nextPage, true);
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
    var url = 'get_messages.php?page=0';
    if (lastMessageId && !force) {
        url += '&last_id=' + encodeURIComponent(lastMessageId);
    }
    xhr.open('GET', url, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            // 没有新消息，跳过
            if (xhr.responseText === 'NOCHANGE') return;

            var messagesContainer = document.getElementById('chatMessages');
            var wasAtBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop < 100;
            var oldScrollHeight = messagesContainer.scrollHeight;
            var oldScrollTop = messagesContainer.scrollTop;

            messagesContainer.innerHTML = xhr.responseText;

            if (wasAtBottom) {
                var st = document.getElementById('scrollTarget');
                if (st) st.scrollIntoView();
            } else {
                var newScrollHeight = messagesContainer.scrollHeight;
                messagesContainer.scrollTop = oldScrollTop + (newScrollHeight - oldScrollHeight);
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

window.addEventListener('resize', function() {
    var inputContainer = document.querySelector('.chat-input-fixed');
    inputContainer.style.width = '100%';
});

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
    setTimeout(function() {
        var st = document.getElementById('scrollTarget');
        if (st) st.scrollIntoView();
    }, 200);
}
</script>
</body>
</html>
