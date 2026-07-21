<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$currentUser = $_SESSION['username'];
$usersDir = 'users';
$userFile = $usersDir . '/' . $currentUser . '.json';

$nickname = $currentUser;
$avatar = '';
$byId = '';
$userTitle = '';
$titleTodayCount = 0;
$titleMaxDaily = 3;

if (file_exists($userFile)) {
    $userData = json_decode(file_get_contents($userFile), true);
    if (is_array($userData)) {
        $nickname = isset($userData['nickname']) ? $userData['nickname'] : $currentUser;
        $avatar = isset($userData['avatar']) ? $userData['avatar'] : '';
        $byId = isset($userData['by_id']) ? $userData['by_id'] : '';
        $userTitle = isset($userData['title']) ? $userData['title'] : '';
        $isAdminUser = isAdmin();
        if ($isAdminUser) {
            $titleMaxDaily = 999;
            $titleTodayCount = 0;
        } else {
            $today = date('Y-m-d');
            $titleTodayCount = ($userData['title_date'] ?? '') === $today ? ($userData['title_count'] ?? 0) : 0;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>用户设置</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}
.container {
    max-width: 500px;
    margin: 0 auto;
}
.header {
    text-align: center;
    color: white;
    margin-bottom: 30px;
}
.header h1 { font-size: 24px; }
.card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.card h2 {
    font-size: 18px;
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 10px;
}
.avatar-preview {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e0e0e0;
    display: block;
    margin: 0 auto 15px;
}
.avatar-default {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #22c55e;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    font-weight: bold;
    margin: 0 auto 15px;
    border: 3px solid #e0e0e0;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #555;
    font-size: 14px;
    font-weight: 500;
}
.form-group input[type="text"] {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 14px;
    transition: border-color 0.3s;
}
.form-group input[type="text"]:focus {
    outline: none;
    border-color: #667eea;
}
.form-group input[type="file"] {
    display: none;
}
.file-label {
    display: inline-block;
    padding: 10px 20px;
    background: #f0f0f0;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    color: #555;
    transition: background 0.3s;
}
.file-label:hover { background: #e0e0e0; }
.btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
.btn-primary:hover { opacity: 0.9; }
.btn-secondary {
    background: #f0f0f0;
    color: #555;
    margin-top: 10px;
}
.btn-secondary:hover { background: #e0e0e0; }
.info-item {
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.info-item:last-child { border-bottom: none; }
.info-item label { color: #888; font-size: 14px; }
.info-item value { color: #333; font-weight: 500; font-size: 14px; }
.status-msg {
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 14px;
    display: none;
}
.status-msg.success { background: #c6f6d5; color: #22543d; display: block; }
.status-msg.error { background: #fed7d7; color: #742a2a; display: block; }
</style>
</head>
<body>
<?php $username = $currentUser; include 'sidebar_component.php'; ?>
<div class="container" style="padding-top:60px;">
    <div class="header">
        <h1>用户设置</h1>
    </div>

    <div class="card">
        <h2>个人信息</h2>
        <div class="info-item">
            <label>用户名</label>
            <value><?php echo htmlspecialchars($currentUser); ?></value>
        </div>
        <div class="info-item">
            <label>BY号</label>
            <value><?php echo htmlspecialchars($byId); ?></value>
        </div>
        <div class="info-item">
            <label>头衔</label>
            <value><?php echo $userTitle ? htmlspecialchars($userTitle) : '<span style="color:#94a3b8;">无</span>'; ?></value>
        </div>
    </div>

    <div class="card">
        <h2>头像设置</h2>
        <div id="avatarStatus" class="status-msg"></div>
            <img src="avatar.php?name=<?php echo urlencode($currentUser); ?>" class="avatar-preview" id="avatarPreview" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="avatar-default" id="avatarPreviewFallback" style="display:none;"><?php echo strtoupper(substr($currentUser, 0, 1)); ?></div>
        <div class="form-group" style="text-align:center;">
            <label class="file-label">
                <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/gif">
                选择图片
            </label>
            <p style="margin-top:8px;font-size:12px;color:#888;">支持 JPG/PNG/GIF，最大 2MB</p>
        </div>
    </div>

    <div class="card">
        <h2>昵称设置</h2>
        <div id="nicknameStatus" class="status-msg"></div>
        <div class="form-group">
            <label>显示昵称</label>
            <input type="text" id="nicknameInput" value="<?php echo htmlspecialchars($nickname); ?>" placeholder="输入新昵称">
        </div>
        <button class="btn btn-primary" id="saveNickname">保存昵称</button>
    </div>

    <div class="card">
        <h2>头衔设置</h2>
        <div id="titleStatus" class="status-msg"></div>
        <div class="form-group">
            <label>自定义头衔 <span style="font-size:12px;color:#888;">（最多20个字符）</span></label>
            <input type="text" id="titleInput" value="<?php echo htmlspecialchars($userTitle); ?>" placeholder="输入头衔，留空取消" maxlength="20">
        </div>
        <p style="font-size:12px;color:#94a3b8;margin-bottom:12px;">今日还可修改 <strong><?php echo $titleMaxDaily - $titleTodayCount; ?></strong> 次</p>
        <button class="btn btn-primary" id="saveTitle">保存头衔</button>
    </div>

    <div class="card" style="text-align:center;">
        <a href="chat.php" class="btn btn-secondary" style="display:inline-block;width:auto;padding:12px 30px;text-decoration:none;">返回聊天室</a>
    </div>
</div>

<script>
document.getElementById('avatarInput').addEventListener('change', function() {
    var file = this.files[0];
    if (!file) return;
    if (!file.type.match(/^image\/(jpeg|png|gif)$/)) {
        showStatus('avatarStatus', '仅支持 JPG/PNG/GIF 格式', 'error');
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        showStatus('avatarStatus', '图片大小不能超过2MB', 'error');
        return;
    }

    var fd = new FormData();
    fd.append('action', 'upload_avatar');
    fd.append('avatar', file);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_profile.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    showStatus('avatarStatus', '头像上传成功', 'success');
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    showStatus('avatarStatus', '上传失败: ' + (data.error || '未知错误'), 'error');
                }
            } catch(e) {
                showStatus('avatarStatus', '上传失败(解析错误): ' + xhr.responseText.substring(0, 100), 'error');
            }
        } else {
            showStatus('avatarStatus', '上传失败(HTTP ' + xhr.status + '): ' + xhr.responseText.substring(0, 100), 'error');
        }
    };
    xhr.onerror = function() {
        showStatus('avatarStatus', '上传失败(网络错误)', 'error');
    };
    xhr.send(fd);
});

document.getElementById('saveNickname').addEventListener('click', function() {
    var nickname = document.getElementById('nicknameInput').value.trim();
    if (!nickname) {
        showStatus('nicknameStatus', '昵称不能为空', 'error');
        return;
    }

    var fd = new FormData();
    fd.append('action', 'update_nickname');
    fd.append('nickname', nickname);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_profile.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    showStatus('nicknameStatus', '昵称保存成功', 'success');
                } else {
                    showStatus('nicknameStatus', '保存失败: ' + (data.error || '未知错误'), 'error');
                }
            } catch(e) {
                showStatus('nicknameStatus', '保存失败', 'error');
            }
        }
    };
    xhr.send(fd);
});

document.getElementById('saveTitle').addEventListener('click', function() {
    var title = document.getElementById('titleInput').value.trim();
    var fd = new FormData();
    fd.append('title', title);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'set_title.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    showStatus('titleStatus', '头衔保存成功', 'success');
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    showStatus('titleStatus', '保存失败: ' + (data.error || '未知错误'), 'error');
                }
            } catch(e) {
                showStatus('titleStatus', '保存失败', 'error');
            }
        } else {
            showStatus('titleStatus', '网络错误', 'error');
        }
    };
    xhr.onerror = function() {
        showStatus('titleStatus', '网络错误', 'error');
    };
    xhr.send(fd);
});

function showStatus(id, msg, type) {
    var el = document.getElementById(id);
    el.textContent = msg;
    el.className = 'status-msg ' + type;
    setTimeout(function() { el.className = 'status-msg'; }, 3000);
}
</script>
</body>
</html>
