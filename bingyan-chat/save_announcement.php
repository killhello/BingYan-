<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit();
}

if (!isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'no_permission']);
    exit();
}

$content = isset($_POST['content']) ? $_POST['content'] : '';
$dataDir = 'data';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

$data = ['content' => $content, 'updated_at' => time()];
file_put_contents($dataDir . '/announcement.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo json_encode(['success' => true]);
