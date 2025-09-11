<?php

/**
 * 简单的Web服务器测试脚本
 * 
 * 测试web_server_example.php的功能
 */

// 引入Composer自动加载器
require_once __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Support\Environment;

// 检查环境是否支持纤程
if (!Environment::checkFiberSupport()) {
    die('当前环境不支持纤程，需要PHP 8.1或更高版本');
}

echo "Testing Web Server...\n";

// 启动服务器（测试模式）
echo "1. Starting server in test mode...\n";
$serverProcess = proc_open(
    'php ' . __DIR__ . '/web_server_example.php test',
    [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ],
    $pipes
);

if (!is_resource($serverProcess)) {
    echo "   ✗ Failed to start server process\n";
    exit(1);
}

// 等待服务器启动
sleep(2);

// 发送HTTP请求
echo "2. Sending HTTP request...\n";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Host: 127.0.0.1:8080\r\n" .
                   "Connection: close\r\n"
    ]
]);

$response = @file_get_contents('http://127.0.0.1:8080/', false, $context);

if ($response !== false) {
    echo "   ✓ HTTP request successful\n";
    if (strpos($response, 'Welcome to Nova Fibers HTTP Server') !== false) {
        echo "   ✓ Home page content verified\n";
    } else {
        echo "   ! Home page content not as expected\n";
    }
} else {
    echo "   ✗ HTTP request failed\n";
}

// 关闭进程
proc_terminate($serverProcess);
proc_close($serverProcess);

echo "Test completed.\n";