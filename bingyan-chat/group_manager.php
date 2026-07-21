<?php
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$currentUser = $_SESSION['username'];
$dataDir = 'data';
$groupsFile = $dataDir . '/groups.json';
$membersFile = $dataDir . '/group_members.json';

function readJson($file) {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function writeJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function generateGroupId() {
    return strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
}

$groups = readJson($groupsFile);
$members = readJson($membersFile);

$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

// 创建群聊
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupName = trim($_POST['group_name']);
    if (empty($groupName)) {
        $error = '群聊名称不能为空';
    } else {
        $groupId = generateGroupId();
        // 确保群号唯一
        $exists = false;
        foreach ($groups as $g) {
            if ($g['group_id'] === $groupId) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $newGroup = [
                'group_id' => $groupId,
                'name' => htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8'),
                'creator' => $currentUser,
                'created_at' => time()
            ];
            $groups[] = $newGroup;
            writeJson($groupsFile, $groups);

            // 创建者为管理员
            $members[] = [
                'group_id' => $groupId,
                'username' => $currentUser,
                'role' => 'admin',
                'joined_at' => time()
            ];
            writeJson($membersFile, $members);

            // 创建群聊消息目录
            $groupChatDir = 'chatlogs/groups/' . $groupId;
            if (!is_dir($groupChatDir)) {
                mkdir($groupChatDir, 0755, true);
            }

            $message = '群聊创建成功！群号：' . $groupId;
        } else {
            $error = '群号生成失败，请重试';
        }
    }
}

// 加入群聊
if ($action === 'join' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupId = strtoupper(trim($_POST['group_id']));
    if (empty($groupId)) {
        $error = '请输入群号';
    } else {
        $groupFound = false;
        foreach ($groups as $g) {
            if ($g['group_id'] === $groupId) {
                $groupFound = true;
                break;
            }
        }
        if (!$groupFound) {
            $error = '群号不存在';
        } else {
            // 检查是否已在群中
            $alreadyIn = false;
            foreach ($members as $m) {
                if ($m['group_id'] === $groupId && $m['username'] === $currentUser) {
                    $alreadyIn = true;
                    break;
                }
            }
            if ($alreadyIn) {
                $error = '你已经在该群聊中了';
            } else {
                $members[] = [
                    'group_id' => $groupId,
                    'username' => $currentUser,
                    'role' => 'member',
                    'joined_at' => time()
                ];
                writeJson($membersFile, $members);
                $message = '成功加入群聊！';
            }
        }
    }
}

// 退出群聊
if ($action === 'leave' && isset($_GET['group_id'])) {
    $groupId = $_GET['group_id'];
    $members = array_filter($members, function($m) use ($groupId, $currentUser) {
        return !($m['group_id'] === $groupId && $m['username'] === $currentUser);
    });
    $members = array_values($members);
    writeJson($membersFile, $members);
    header('Location: group_manager.php');
    exit();
}

// 解散群聊（仅管理员）
if ($action === 'disband' && isset($_GET['group_id'])) {
    $groupId = $_GET['group_id'];
    // 检查是否为管理员
    $isAdmin = false;
    foreach ($members as $m) {
        if ($m['group_id'] === $groupId && $m['username'] === $currentUser && $m['role'] === 'admin') {
            $isAdmin = true;
            break;
        }
    }
    if (!$isAdmin) {
        $error = '只有群管理员才能解散群聊';
    } else {
        // 移除所有成员
        $members = array_filter($members, function($m) use ($groupId) {
            return $m['group_id'] !== $groupId;
        });
        $members = array_values($members);
        writeJson($membersFile, $members);

        // 删除群聊
        $groups = array_filter($groups, function($g) use ($groupId) {
            return $g['group_id'] !== $groupId;
        });
        $groups = array_values($groups);
        writeJson($groupsFile, $groups);

        // 删除聊天记录目录
        $groupChatDir = 'chatlogs/groups/' . $groupId;
        if (is_dir($groupChatDir)) {
            array_map('unlink', glob($groupChatDir . '/*'));
            rmdir($groupChatDir);
        }

        header('Location: group_manager.php');
        exit();
    }
}

// 获取当前用户的群聊列表
$myGroups = [];
foreach ($members as $m) {
    if ($m['username'] === $currentUser) {
        foreach ($groups as $g) {
            if ($g['group_id'] === $m['group_id']) {
                $g['my_role'] = $m['role'];
                $myGroups[] = $g;
                break;
            }
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
    <title>群聊管理</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .nav-links {
            text-align: center;
            margin-bottom: 20px;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            transition: background 0.3s;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.3); }
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card h2 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover { opacity: 0.9; }
        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }
        .btn-danger {
            background: #e53e3e;
            color: white;
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-success { background: #c6f6d5; color: #22543d; }
        .alert-error { background: #fed7d7; color: #742a2a; }
        .group-list {
            display: grid;
            gap: 15px;
        }
        .group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f7fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .group-info h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }
        .group-info p {
            font-size: 13px;
            color: #718096;
        }
        .group-actions {
            display: flex;
            gap: 10px;
        }
        .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .role-admin { background: #fed7d7; color: #c53030; }
        .role-member { background: #bee3f8; color: #2b6cb0; }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
    </style>
</head>
<body>
    <?php include 'sidebar_component.php'; ?>
    <div class="container" style="padding-top:60px;">
        <div class="header">
            <h1>群聊管理</h1>
            <p>管理你的群聊</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>创建群聊</h2>
            <form method="POST" action="group_manager.php?action=create">
                <div class="form-group">
                    <label>群聊名称</label>
                    <input type="text" name="group_name" placeholder="请输入群聊名称" required maxlength="50">
                </div>
                <button type="submit" class="btn btn-primary">创建群聊</button>
            </form>
        </div>

        <div class="card">
            <h2>加入群聊</h2>
            <form method="POST" action="group_manager.php?action=join">
                <div class="form-group">
                    <label>群号</label>
                    <input type="text" name="group_id" placeholder="请输入8位群号" required maxlength="8" style="text-transform:uppercase;">
                </div>
                <button type="submit" class="btn btn-success">加入群聊</button>
            </form>
        </div>

        <div class="card">
            <h2>我的群聊</h2>
            <?php if (empty($myGroups)): ?>
                <div class="empty-state">
                    <p>你还没有加入任何群聊</p>
                    <p>创建或加入一个群聊开始聊天吧</p>
                </div>
            <?php else: ?>
                <div class="group-list">
                    <?php foreach ($myGroups as $group): ?>
                        <div class="group-item">
                            <div class="group-info">
                                <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                                <p>群号：<?php echo $group['group_id']; ?> | 创建者：<?php echo htmlspecialchars($group['creator']); ?></p>
                                <span class="role-badge role-<?php echo $group['my_role']; ?>">
                                    <?php echo $group['my_role'] === 'admin' ? '管理员' : '成员'; ?>
                                </span>
                            </div>
                            <div class="group-actions">
                                <a href="group_chat.php?group_id=<?php echo $group['group_id']; ?>" class="btn btn-primary">进入群聊</a>
                                <?php if ($group['my_role'] === 'admin'): ?>
                                    <a href="group_admin.php?group_id=<?php echo $group['group_id']; ?>" class="btn btn-success">管理</a>
                                <?php endif; ?>
                                <a href="group_manager.php?action=leave&group_id=<?php echo $group['group_id']; ?>" class="btn btn-danger" onclick="return confirm('确定要退出该群聊吗？')">退出</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
