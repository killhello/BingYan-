<?php
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$currentUser = $_SESSION['username'];
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

function writeJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
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

// 检查是否是管理员
$isAdmin = false;
foreach ($members as $m) {
    if ($m['group_id'] === $groupId && $m['username'] === $currentUser && $m['role'] === 'admin') {
        $isAdmin = true;
        break;
    }
}

if (!$isAdmin) {
    header('Location: group_chat.php?group_id=' . $groupId);
    exit();
}

$message = '';
$error = '';

// 处理操作
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'remove_member' && isset($_GET['username'])) {
    $targetUser = $_GET['username'];
    // 不能移除自己
    if ($targetUser === $currentUser) {
        $error = '不能移除自己';
    } else {
        $members = array_filter($members, function($m) use ($groupId, $targetUser) {
            return !($m['group_id'] === $groupId && $m['username'] === $targetUser);
        });
        $members = array_values($members);
        writeJson($membersFile, $members);
        $message = '已移除成员：' . htmlspecialchars($targetUser);
    }
}

if ($action === 'set_admin' && isset($_GET['username'])) {
    $targetUser = $_GET['username'];
    foreach ($members as &$m) {
        if ($m['group_id'] === $groupId && $m['username'] === $targetUser) {
            $m['role'] = 'admin';
            break;
        }
    }
    unset($m);
    writeJson($membersFile, $members);
    $message = '已设置 ' . htmlspecialchars($targetUser) . ' 为管理员';
}

if ($action === 'set_member' && isset($_GET['username'])) {
    $targetUser = $_GET['username'];
    // 不能降级自己
    if ($targetUser === $currentUser) {
        $error = '不能降级自己';
    } else {
        foreach ($members as &$m) {
            if ($m['group_id'] === $groupId && $m['username'] === $targetUser) {
                $m['role'] = 'member';
                break;
            }
        }
        unset($m);
        writeJson($membersFile, $members);
        $message = '已降级 ' . htmlspecialchars($targetUser) . ' 为普通成员';
    }
}

if ($action === 'batch_remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernames = isset($_POST['usernames']) ? $_POST['usernames'] : [];
    $removed = 0;
    foreach ($usernames as $targetUser) {
        if ($targetUser === $currentUser) continue;
        $members = array_filter($members, function($m) use ($groupId, $targetUser) {
            return !($m['group_id'] === $groupId && $m['username'] === $targetUser);
        });
        $members = array_values($members);
        $removed++;
    }
    writeJson($membersFile, $members);
    $message = '已批量移除 ' . $removed . ' 个成员';
}

// 获取群成员列表
$groupMembers = [];
foreach ($members as $m) {
    if ($m['group_id'] === $groupId) {
        $groupMembers[] = $m;
    }
}

$username = $currentUser;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($group['name']); ?> - 群管理</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { opacity: 0.9; }
        .nav-links {
            text-align: center;
            margin-bottom: 20px;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            margin: 0 10px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 14px;
        }
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert-success { background: #c6f6d5; color: #22543d; }
        .alert-error { background: #fed7d7; color: #742a2a; }
        .member-table {
            width: 100%;
            border-collapse: collapse;
        }
        .member-table th,
        .member-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .member-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }
        .member-table tr:hover {
            background: #f7fafc;
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
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            margin-right: 5px;
        }
        .btn-danger { background: #e53e3e; color: white; }
        .btn-success { background: #48bb78; color: white; }
        .btn-warning { background: #ed8936; color: white; }
        .btn-primary { background: #667eea; color: white; }
        .batch-actions {
            margin-bottom: 15px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }
        .batch-actions label {
            margin-right: 15px;
            cursor: pointer;
        }
        .group-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-item {
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }
        .info-item label {
            font-size: 12px;
            color: #718096;
            display: block;
            margin-bottom: 5px;
        }
        .info-item value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
    </style>
</head>
<body>
    <?php include 'sidebar_component.php'; ?>
    <div class="container" style="padding-top:60px;">
        <div class="header">
            <h1><?php echo htmlspecialchars($group['name']); ?></h1>
            <p>群管理后台</p>
        </div>

        <div class="nav-links">
            <a href="group_chat.php?group_id=<?php echo $groupId; ?>">返回群聊</a>
            <a href="group_manager.php">群聊列表</a>
            <a href="chat.php">公共聊天室</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>群聊信息</h2>
            <div class="group-info">
                <div class="info-item">
                    <label>群号</label>
                    <value><?php echo $group['group_id']; ?></value>
                </div>
                <div class="info-item">
                    <label>群名称</label>
                    <value><?php echo htmlspecialchars($group['name']); ?></value>
                </div>
                <div class="info-item">
                    <label>创建者</label>
                    <value><?php echo htmlspecialchars($group['creator']); ?></value>
                </div>
                <div class="info-item">
                    <label>成员数</label>
                    <value><?php echo count($groupMembers); ?> 人</value>
                </div>
                <div class="info-item">
                    <label>创建时间</label>
                    <value><?php echo date('Y-m-d H:i', $group['created_at']); ?></value>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>成员管理</h2>
            <form method="POST" action="group_admin.php?group_id=<?php echo $groupId; ?>&action=batch_remove" id="batchForm">
                <div class="batch-actions">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('确定要移除选中的成员吗？')">批量移除</button>
                </div>
                <table class="member-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>用户名</th>
                            <th>身份</th>
                            <th>加入时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groupMembers as $member): ?>
                            <tr>
                                <td>
                                    <?php if ($member['username'] !== $currentUser): ?>
                                        <input type="checkbox" name="usernames[]" value="<?php echo htmlspecialchars($member['username']); ?>">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($member['username']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $member['role']; ?>">
                                        <?php echo $member['role'] === 'admin' ? '管理员' : '成员'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i', $member['joined_at']); ?></td>
                                <td>
                                    <?php if ($member['username'] !== $currentUser): ?>
                                        <?php if ($member['role'] === 'member'): ?>
                                            <a href="group_admin.php?group_id=<?php echo $groupId; ?>&action=set_admin&username=<?php echo urlencode($member['username']); ?>" class="btn btn-success">设为管理员</a>
                                        <?php else: ?>
                                            <a href="group_admin.php?group_id=<?php echo $groupId; ?>&action=set_member&username=<?php echo urlencode($member['username']); ?>" class="btn btn-warning">降为成员</a>
                                        <?php endif; ?>
                                        <a href="group_admin.php?group_id=<?php echo $groupId; ?>&action=remove_member&username=<?php echo urlencode($member['username']); ?>" class="btn btn-danger" onclick="return confirm('确定要移除该成员吗？')">移除</a>
                                    <?php else: ?>
                                        <span style="color:#718096;">自己</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('selectAll').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('input[name="usernames[]"]');
        checkboxes.forEach(function(cb) {
            cb.checked = this.checked;
        }, this);
    });
    </script>
</body>
</html>
