<?php
define('ADMIN_USERNAMES', ['BingYan']);

function isAdmin() {
    return isset($_SESSION['username']) && in_array($_SESSION['username'], ADMIN_USERNAMES);
}

function isAdminUser($username) {
    return in_array($username, ADMIN_USERNAMES);
}

function getUserData($username) {
    static $cache = array();
    if (isset($cache[$username])) return $cache[$username];
    $file = 'users/' . $username . '.json';
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) return null;
    if (!isset($data['message_count'])) $data['message_count'] = 0;
    if (!isset($data['title'])) $data['title'] = '';
    if (!isset($data['title_date'])) $data['title_date'] = '';
    if (!isset($data['title_count'])) $data['title_count'] = 0;
    if (!isset($data['banned'])) $data['banned'] = false;
    if (!isset($data['muted'])) $data['muted'] = false;
    $cache[$username] = $data;
    return $data;
}

function saveUserData($username, $data) {
    $file = 'users/' . $username . '.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function getLevel($messageCount) {
    return min(floor($messageCount / 10) + 1, 10);
}

function getLevelImagePath($level) {
    $level = max(1, min(10, (int)$level));
    if ($level === 1) return 'bead-pattern .png';
    if ($level === 2) return 'bead-pattern(1).png';
    return 'bead-pattern (' . ($level - 1) . ').png';
}

function getLevelLabel($messageCount) {
    $level = getLevel($messageCount);
    $path = getLevelImagePath($level);
    return '<img class="level-img" src="' . htmlspecialchars($path, ENT_QUOTES) . '" alt="Lv' . $level . '" width="60" height="48">';
}

function getUserLevelAndTitle($username) {
    $data = getUserData($username);
    if (!$data) return array('level' => 'Lv1', 'title' => '');
    if (in_array($username, ADMIN_USERNAMES)) {
        return array(
            'level' => '<img class="level-img" src="BingYan.png" alt="Admin" width="60" height="48">',
            'title' => $data['title'] ?? ''
        );
    }
    $level = getLevelLabel($data['message_count']);
    $title = $data['title'] ?? '';
    return array('level' => $level, 'title' => $title);
}

function formatUserDisplay($username) {
    $info = getUserLevelAndTitle($username);
    $display = htmlspecialchars($username) . ' <span class="user-level-badge">' . $info['level'] . '</span>';
    if (!empty($info['title'])) {
        $display .= ' <span class="user-title-badge">[' . htmlspecialchars($info['title']) . ']</span>';
    }
    return $display;
}
