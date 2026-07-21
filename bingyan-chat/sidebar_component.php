<?php
require_once __DIR__ . '/config.php';
// 侧边栏组件 - 需要在调用前设置 $username
$dataDir = 'data';
$friendsFile = $dataDir . '/friends.json';
$usersDir = 'users';
$myFriends = [];

// 读取当前用户信息
$userFile = $usersDir . '/' . $username . '.json';
$myAvatar = '';
$myNickname = $username;
if (file_exists($userFile)) {
    $myUserData = json_decode(file_get_contents($userFile), true);
    if (is_array($myUserData)) {
        $myAvatar = isset($myUserData['avatar']) ? $myUserData['avatar'] : '';
        $myNickname = isset($myUserData['nickname']) ? $myUserData['nickname'] : $username;
    }
}

if (file_exists($friendsFile)) {
    $friends = json_decode(file_get_contents($friendsFile), true);
    if (is_array($friends)) {
        foreach ($friends as $f) {
            if ($f['user1'] === $username) {
                $uf = $usersDir . '/' . $f['user2'] . '.json';
                $ud = file_exists($uf) ? json_decode(file_get_contents($uf), true) : null;
                $remark = !empty($f['remark1']) ? $f['remark1'] : $f['user2'];
                $myFriends[] = ['username' => $f['user2'], 'remark' => $remark, 'by_id' => $ud && isset($ud['by_id']) ? $ud['by_id'] : ''];
            } elseif ($f['user2'] === $username) {
                $uf = $usersDir . '/' . $f['user1'] . '.json';
                $ud = file_exists($uf) ? json_decode(file_get_contents($uf), true) : null;
                $remark = !empty($f['remark2']) ? $f['remark2'] : $f['user1'];
                $myFriends[] = ['username' => $f['user1'], 'remark' => $remark, 'by_id' => $ud && isset($ud['by_id']) ? $ud['by_id'] : ''];
            }
        }
    }
}
?>
<!-- 左侧抽屉导航 -->
<button class="sidebar-toggle" id="sidebarToggle" title="菜单">&#9776;</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="sidebar" id="sidebar">
    <button class="sidebar-close" id="sidebarClose">&times;</button>
<?php
$sideUserData = getUserData($username);
$sideLevel = isAdminUser($username)
    ? '<img class="level-img" src="BingYan.png" alt="Admin" width="60" height="48">'
    : ($sideUserData ? getLevelLabel($sideUserData['message_count']) : 'Lv1');
$sideTitle = $sideUserData && !empty($sideUserData['title']) ? htmlspecialchars($sideUserData['title']) : '';
?>
    <div class="sidebar-user">
        <img src="avatar.php?name=<?php echo urlencode($username); ?>" class="sidebar-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <div class="sidebar-avatar sidebar-avatar-default" style="display:none;"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
        <h3><?php echo htmlspecialchars($myNickname); ?></h3>
        <p><?php echo htmlspecialchars($username); ?> <span class="user-level-badge"><?php echo $sideLevel; ?></span><?php if ($sideTitle): ?> <span class="user-title-badge">[<?php echo $sideTitle; ?>]</span><?php endif; ?></p>
    </div>
    <ul class="sidebar-menu">
        <li><a href="chat.php"><span class="icon">&#128172;</span>公共聊天室</a></li>
        <li><a href="group_manager.php"><span class="icon">&#128101;</span>群聊</a></li>
        <?php if (!empty($myFriends)): ?>
        <li class="sidebar-divider"></li>
        <?php foreach ($myFriends as $fr): ?>
        <li><a href="friend_chat.php?friend=<?php echo urlencode($fr['username']); ?>"><span class="icon">&#128100;</span><?php echo htmlspecialchars($fr['remark']); ?></a></li>
        <?php endforeach; ?>
        <?php endif; ?>
        <li class="sidebar-divider"></li>
        <li><a href="user_settings.php"><span class="icon">&#9881;</span>用户设置</a></li>
        <li><a href="friends.php"><span class="icon">&#128221;</span>好友管理</a></li>
        <li><a href="logout.php" onclick="return confirm('确定要退出登录吗？')"><span class="icon">&#128682;</span>退出登录</a></li>
        <?php if (isAdmin()): ?>
        <li class="sidebar-divider"></li>
        <li><a href="#" onclick="toggleAdminPanel();return false;"><span class="icon">&#128272;</span>用户管理</a></li>
    </ul>
    <div class="admin-panel" id="adminPanel">
        <div class="admin-panel-header">
            <span>所有用户</span>
            <button class="admin-refresh-btn" onclick="loadUsers()" title="刷新">&#8635;</button>
        </div>
        <div class="admin-user-list" id="adminUserList">
            <div class="admin-loading">加载中...</div>
        </div>
    </div>
    <?php else: ?>
    </ul>
    <?php endif; ?>
</div>

<style>
.sidebar-toggle {
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1001;
    width: 42px;
    height: 42px;
    background: rgba(255,255,255,0.6);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.8);
    border-radius: 12px;
    cursor: pointer;
    font-size: 20px;
    color: #475569;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}
.sidebar-toggle:hover {
    background: rgba(255,255,255,0.8);
    transform: scale(1.05);
}
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.15);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 1001;
}
.sidebar-overlay.active { display: block; }
.sidebar {
    position: fixed;
    top: 0;
    left: -280px;
    width: 280px;
    height: 100%;
    background: rgba(255,255,255,0.75);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-right: 1px solid rgba(255,255,255,0.9);
    z-index: 1002;
    transition: left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    padding-top: 60px;
    overflow-y: auto;
    box-shadow: 4px 0 32px rgba(0,0,0,0.06);
}
.sidebar.active { left: 0; }
.sidebar-close {
    position: absolute;
    top: 15px;
    right: 15px;
    color: #94a3b8;
    font-size: 24px;
    cursor: pointer;
    background: none;
    border: none;
    transition: all 0.3s;
}
.sidebar-close:hover { color: #1e293b; transform: rotate(90deg); }
.sidebar-user {
    padding: 20px;
    text-align: center;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    margin-bottom: 10px;
}
.sidebar-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.9);
    display: block;
    margin: 0 auto 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.sidebar-avatar-default {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
}
.sidebar-user h3 {
    font-size: 16px;
    color: #1e293b;
    margin-top: 8px;
    font-weight: 600;
}
.sidebar-user p {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 2px;
}
.sidebar-menu {
    list-style: none;
    padding: 0;
}
.sidebar-menu li a {
    display: flex;
    align-items: center;
    padding: 14px 20px;
    color: #475569;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.25s;
    margin: 2px 8px;
    border-radius: 10px;
}
.sidebar-menu li a:hover {
    background: rgba(255,255,255,0.8);
    color: #1e293b;
}
.sidebar-menu li a .icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    margin-right: 12px;
    font-size: 16px;
    background: rgba(255,255,255,0.7);
    border-radius: 8px;
}
.sidebar-divider {
    height: 1px;
    background: rgba(0,0,0,0.06);
    margin: 8px 20px;
}
.admin-panel {
    padding: 0 16px 16px;
    display: none;
}
.admin-panel.active { display: block; }
.admin-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    padding: 8px 4px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    margin-bottom: 8px;
}
.admin-refresh-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    color: #94a3b8;
    padding: 2px 6px;
    border-radius: 4px;
    transition: all 0.3s;
}
.admin-refresh-btn:hover { color: #1e293b; background: rgba(0,0,0,0.05); }
.admin-user-list {
    max-height: 300px;
    overflow-y: auto;
    font-size: 13px;
}
.admin-user-item {
    display: flex;
    align-items: center;
    padding: 6px 4px;
    border-bottom: 1px solid rgba(0,0,0,0.04);
    gap: 4px;
    flex-wrap: wrap;
}
.admin-user-name {
    flex: 1;
    min-width: 60px;
    font-weight: 500;
    color: #1e293b;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.admin-user-badge {
    display: inline-block;
    font-size: 10px;
    padding: 1px 5px;
    border-radius: 6px;
    font-weight: 500;
}
.admin-user-badge.banned { background: #fee2e2; color: #dc2626; }
.admin-user-badge.muted { background: #fef3c7; color: #d97706; }
.admin-user-item .admin-btn {
    font-size: 11px;
    padding: 2px 6px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}
.admin-user-item .admin-btn:hover { opacity: 0.8; }
.admin-btn-ban { background: #fee2e2; color: #dc2626; }
.admin-btn-unban { background: #d1fae5; color: #059669; }
.admin-btn-mute { background: #fef3c7; color: #d97706; }
.admin-btn-unmute { background: #d1fae5; color: #059669; }
.admin-btn-delete { background: #fecaca; color: #b91c1c; }
.admin-loading { text-align: center; color: #94a3b8; padding: 20px; font-size: 13px; }
</style>

<script>
var sidebar = document.getElementById('sidebar');
var sidebarOverlay = document.getElementById('sidebarOverlay');
var sidebarToggle = document.getElementById('sidebarToggle');
var sidebarClose = document.getElementById('sidebarClose');
function openSidebar() { sidebar.classList.add('active'); sidebarOverlay.classList.add('active'); }
function closeSidebar() { sidebar.classList.remove('active'); sidebarOverlay.classList.remove('active'); }
if (sidebarToggle) sidebarToggle.addEventListener('click', openSidebar);
if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
<?php if (isAdmin()): ?>
function toggleAdminPanel() {
    var panel = document.getElementById('adminPanel');
    if (!panel) return;
    panel.classList.toggle('active');
    if (panel.classList.contains('active')) {
        panel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        setTimeout(function() { loadUsers(); }, 100);
    }
}

function loadUsers() {
    var list = document.getElementById('adminUserList');
    if (!list) return;
    list.innerHTML = '<div class="admin-loading">加载中...</div>';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'admin_action.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    renderUserList(data.users);
                } else {
                    list.innerHTML = '<div class="admin-loading">加载失败: ' + (data.error || '未知错误') + '</div>';
                }
            } catch(e) {
                list.innerHTML = '<div class="admin-loading">响应解析失败</div>';
            }
        } else {
            list.innerHTML = '<div class="admin-loading">请求失败 (HTTP ' + xhr.status + ')</div>';
        }
    };
    xhr.onerror = function() {
        list.innerHTML = '<div class="admin-loading">网络错误，请检查控制台</div>';
    };
    xhr.send('action=list');
}

function renderUserList(users) {
    var list = document.getElementById('adminUserList');
    var html = '';
    for (var i = 0; i < users.length; i++) {
        var u = users[i];
        var badges = '';
        if (u.banned) badges += '<span class="admin-user-badge banned">封禁</span> ';
        if (u.muted) badges += '<span class="admin-user-badge muted">禁言</span> ';
        html += '<div class="admin-user-item">' +
            '<span class="admin-user-name" title="' + u.username + '">' + u.username + '</span>' +
            badges +
            (u.banned
                ? '<button class="admin-btn admin-btn-unban" onclick="adminAction(\'unban\',\'' + u.username + '\')">解封</button>'
                : '<button class="admin-btn admin-btn-ban" onclick="adminAction(\'ban\',\'' + u.username + '\')">封禁</button>') +
            (u.muted
                ? '<button class="admin-btn admin-btn-unmute" onclick="adminAction(\'unmute\',\'' + u.username + '\')">解禁</button>'
                : '<button class="admin-btn admin-btn-mute" onclick="adminAction(\'mute\',\'' + u.username + '\')">禁言</button>') +
            '<button class="admin-btn admin-btn-delete" onclick="adminAction(\'delete\',\'' + u.username + '\')">注销</button>' +
            '</div>';
    }
    list.innerHTML = html;
}

function adminAction(action, username) {
    if (action === 'delete' && !confirm('确定要注销用户 "' + username + '" 吗？')) return;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'admin_action.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    loadUsers();
                } else {
                    alert('操作失败: ' + (data.error || '未知错误'));
                }
            } catch(e) {
                alert('响应解析失败');
            }
        } else {
            alert('请求失败');
        }
    };
    xhr.onerror = function() {
        alert('网络错误');
    };
    xhr.send('action=' + action + '&username=' + encodeURIComponent(username));
}
<?php endif; ?>
</script>
