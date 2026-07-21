<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit();
}

$prompt = isset($_POST['prompt']) ? trim($_POST['prompt']) : '';
if (empty($prompt)) {
    echo json_encode(['success' => false, 'error' => 'empty_prompt']);
    exit();
}

set_time_limit(130);
ignore_user_abort(true);

$apiKey = 'YOUR API KEY';

$postData = json_encode([
    'model' => 'gpt-5.4',
    'stream' => true,
    'messages' => [
        ['role' => 'system', 'content' => '你是一个有用的AI助手，请用中文回答用户的问题。'],
        ['role' => 'user', 'content' => $prompt]
    ]
]);

$fullContent = '';

$ch = curl_init('https://uuapi.net/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey,
    'User-Agent: codex_cli_rs/0.77.0 (Windows 10.0.26100; x86_64) WindowsTerminal'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// 流式读取回调
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$fullContent) {
    $lines = explode("\n", $data);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, 'data: ') !== 0) continue;
        $json = substr($line, 6);
        if ($json === '[DONE]') continue;
        $chunk = json_decode($json, true);
        if (isset($chunk['choices'][0]['delta']['content'])) {
            $fullContent .= $chunk['choices'][0]['delta']['content'];
        }
    }
    return strlen($data);
});

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'curl_error: ' . $curlError]);
    exit();
}

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => 'http_error: ' . $httpCode]);
    exit();
}

if (!empty($fullContent)) {
    echo json_encode(['success' => true, 'content' => $fullContent]);
} else {
    echo json_encode(['success' => false, 'error' => 'empty_response']);
}
