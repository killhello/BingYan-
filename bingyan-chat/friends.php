<?php
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$currentUser = $_SESSION['username'];
$dataDir = 'data';
$friendsFile = $dataDir . '/friends.json';
$usersDir = 'users';

function readJson($file) {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function writeJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

// 获取当前用户的BY号
function getUserById($username) {
    global $usersDir;
    $file = $usersDir . '/' . $username . '.json';
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    return $data;
}

// 通过BY号查找用户
function findUserByBYId($byId) {
    global $usersDir;
    $files = glob($usersDir . '/*.json');
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (isset($data['by_id']) && strtoupper($data['by_id']) === strtoupper($byId)) {
            return $data;
        }
    }
    return null;
}

// 确保当前用户有BY号
$myData = getUserById($currentUser);
if ($myData && empty($myData['by_id'])) {
    $myData['by_id'] = 'BY' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
    file_put_contents($usersDir . '/' . $currentUser . '.json', json_encode($myData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
$myById = $myData ? $myData['by_id'] : '';

$friends = readJson($friendsFile);
$message = '';
$error = '';

// 添加好友
if (isset($_GET['action']) && $_GET['action'] === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $byId = strtoupper(trim($_POST['by_id']));
    if (empty($byId)) {
        $error = '请输入BY号';
    } elseif (strtoupper($byId) === strtoupper($myById)) {
        $error = '不能添加自己为好友';
    } else {
        $targetUser = findUserByBYId($byId);
        if (!$targetUser) {
            $error = 'BY号不存在';
        } else {
            $targetUsername = $targetUser['username'];
            // 检查是否已是好友
            $alreadyFriend = false;
            foreach ($friends as $f) {
                if (($f['user1'] === $currentUser && $f['user2'] === $targetUsername) ||
                    ($f['user1'] === $targetUsername && $f['user2'] === $currentUser)) {
                    $alreadyFriend = true;
                    break;
                }
            }
            if ($alreadyFriend) {
                $error = '已经是好友了';
            } else {
                $friends[] = [
                    'user1' => $currentUser,
                    'user2' => $targetUsername,
                    'remark1' => '',
                    'remark2' => '',
                    'created_at' => time()
                ];
                writeJson($friendsFile, $friends);
                $message = '添加好友成功：' . htmlspecialchars($targetUsername);
            }
        }
    }
}

// 删除好友
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['friend'])) {
    $friendUsername = $_GET['friend'];
    $friends = array_filter($friends, function($f) use ($currentUser, $friendUsername) {
        return !(($f['user1'] === $currentUser && $f['user2'] === $friendUsername) ||
                 ($f['user1'] === $friendUsername && $f['user2'] === $currentUser));
    });
    $friends = array_values($friends);
    writeJson($friendsFile, $friends);
    header('Location: friends.php');
    exit();
}

// 设置备注
if (isset($_GET['action']) && $_GET['action'] === 'remark' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $friendUsername = $_POST['friend'];
    $remark = trim($_POST['remark']);
    foreach ($friends as &$f) {
        if ($f['user1'] === $currentUser && $f['user2'] === $friendUsername) {
            $f['remark1'] = $remark;
            break;
        } elseif ($f['user1'] === $friendUsername && $f['user2'] === $currentUser) {
            $f['remark2'] = $remark;
            break;
        }
    }
    unset($f);
    writeJson($friendsFile, $friends);
    $message = '备注修改成功';
}

// 获取好友列表
$myFriends = [];
foreach ($friends as $f) {
    if ($f['user1'] === $currentUser) {
        $friendData = getUserById($f['user2']);
        if ($friendData) {
            $myFriends[] = [
                'username' => $f['user2'],
                'by_id' => isset($friendData['by_id']) ? $friendData['by_id'] : '',
                'remark' => !empty($f['remark1']) ? $f['remark1'] : $f['user2'],
                'created_at' => $f['created_at']
            ];
        }
    } elseif ($f['user2'] === $currentUser) {
        $friendData = getUserById($f['user1']);
        if ($friendData) {
            $myFriends[] = [
                'username' => $f['user1'],
                'by_id' => isset($friendData['by_id']) ? $friendData['by_id'] : '',
                'remark' => !empty($f['remark2']) ? $f['remark2'] : $f['user1'],
                'created_at' => $f['created_at']
            ];
        }
    }
}
$username = $currentUser;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>好友管理</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        .header h1 { font-size: 28px; margin-bottom: 5px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card h2 { font-size: 20px; margin-bottom: 15px; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-success { background: #48bb78; color: white; }
        .btn-danger { background: #e53e3e; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-success { background: #c6f6d5; color: #22543d; }
        .alert-error { background: #fed7d7; color: #742a2a; }
        .friend-list { display: grid; gap: 12px; }
        .friend-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f7fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .friend-info h3 { font-size: 16px; color: #333; margin-bottom: 3px; }
        .friend-info p { font-size: 13px; color: #718096; }
        .friend-info .remark { color: #667eea; font-size: 12px; }
        .friend-actions { display: flex; gap: 8px; }
        .empty-state { text-align: center; padding: 40px; color: #718096; }
        .my-id {
            background: #ebf8ff;
            border: 1px solid #bee3f8;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        .my-id h3 { color: #2b6cb0; margin-bottom: 5px; }
        .my-id .id-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c5282;
            letter-spacing: 2px;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white;
            border-radius: 15px;
            padding: 25px;
            width: 90%;
            max-width: 400px;
        }
        .modal h3 { margin-bottom: 15px; }
        .modal-actions { margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end; }
    </style>
</head>
<body>
    <?php include 'sidebar_component.php'; ?>
    <div class="container" style="padding-top:60px;">
        <div class="header">
            <h1>好友管理</h1>
            <p>管理你的好友列表</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="my-id">
            <h3>我的BY号</h3>
            <div class="id-value"><?php echo htmlspecialchars($myById); ?></div>
            <p style="font-size:12px;color:#718096;margin-top:5px;">分享给好友，让他们通过此BY号添加你</p>
        </div>

        <div class="card">
            <h2>添加好友</h2>
            <form method="POST" action="friends.php?action=add">
                <div class="form-group">
                    <label>对方BY号</label>
                    <input type="text" name="by_id" placeholder="请输入BY号，如 BYABC123" required maxlength="8" style="text-transform:uppercase;">
                </div>
                <button type="submit" class="btn btn-primary">添加好友</button>
            </form>
        </div>

        <div class="card">
            <h2>我的好友 (<?php echo count($myFriends); ?>)</h2>
            <?php if (empty($myFriends)): ?>
                <div class="empty-state">
                    <p>还没有好友</p>
                    <p>通过BY号添加好友开始聊天吧</p>
                </div>
            <?php else: ?>
                <div class="friend-list">
                    <?php foreach ($myFriends as $friend): ?>
                        <div class="friend-item">
                            <div class="friend-info">
                                <h3><?php echo htmlspecialchars($friend['remark']); ?></h3>
                                <p>BY号：<?php echo htmlspecialchars($friend['by_id']); ?></p>
                                <?php if ($friend['remark'] !== $friend['username']): ?>
                                    <span class="remark">备注：<?php echo htmlspecialchars($friend['remark']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="friend-actions">
                                <a href="friend_chat.php?friend=<?php echo urlencode($friend['username']); ?>" class="btn btn-primary btn-sm">聊天</a>
                                <button class="btn btn-success btn-sm" onclick="openRemarkModal('<?php echo htmlspecialchars($friend['username']); ?>', '<?php echo htmlspecialchars($friend['remark']); ?>')">备注</button>
                                <a href="friends.php?action=delete&friend=<?php echo urlencode($friend['username']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('确定要删除该好友吗？')">删除</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 设置备注弹窗 -->
    <div class="modal-overlay" id="remarkModal">
        <div class="modal">
            <h3>设置备注</h3>
            <form method="POST" action="friends.php?action=remark">
                <input type="hidden" name="friend" id="remarkFriend">
                <div class="form-group">
                    <label>备注名</label>
                    <input type="text" name="remark" id="remarkInput" placeholder="请输入备注名" maxlength="20">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-danger btn-sm" onclick="closeRemarkModal()">取消</button>
                    <button type="submit" class="btn btn-primary btn-sm">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openRemarkModal(username, currentRemark) {
        document.getElementById('remarkFriend').value = username;
        document.getElementById('remarkInput').value = currentRemark === username ? '' : currentRemark;
        document.getElementById('remarkModal').classList.add('active');
    }
    function closeRemarkModal() {
        document.getElementById('remarkModal').classList.remove('active');
    }
    document.getElementById('remarkModal').addEventListener('click', function(e) {
        if (e.target === this) closeRemarkModal();
    });
    </script>
</body>
</html>
