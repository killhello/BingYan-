<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit();
}

$announcementFile = 'data/announcement.json';

if (file_exists($announcementFile)) {
    $data = json_decode(file_get_contents($announcementFile), true);
    if (is_array($data)) {
        echo json_encode(['success' => true, 'content' => $data['content'] ?? '', 'updated_at' => $data['updated_at'] ?? 0], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

echo json_encode(['success' => true, 'content' => '', 'updated_at' => 0]);
