<?php
session_start();
require_once __DIR__ . '/geetest_lib.php';

$config = require __DIR__ . '/geetest_config.php';
$lib = new GeeTestLib($config['id'], $config['key']);

header('Content-Type: application/json');
echo json_encode($lib->register());
