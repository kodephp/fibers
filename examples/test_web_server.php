<?php

/**
 * Web服务器测试脚本
 * 
 * 测试web_server_example.php的功能
 */

echo "Testing Web Server...\n";

function getUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$response, $httpCode];
}

// 测试主页
echo "1. Testing home page...\n";
list($homepage, $httpCode) = getUrl('http://127.0.0.1:8080/');
if ($httpCode === 200 && strpos($homepage, 'Welcome to Nova Fibers HTTP Server') !== false) {
    echo "   ✓ Home page test passed\n";
} else {
    echo "   ✗ Home page test failed (HTTP code: $httpCode)\n";
}

// 测试并发任务页面
echo "2. Testing concurrent tasks page...\n";
list($fibersPage, $httpCode) = getUrl('http://127.0.0.1:8080/fibers');
if ($httpCode === 200 && strpos($fibersPage, 'Concurrent Tasks Results') !== false) {
    echo "   ✓ Concurrent tasks page test passed\n";
} else {
    echo "   ✗ Concurrent tasks page test failed (HTTP code: $httpCode)\n";
}

// 测试超时控制页面
echo "3. Testing timeout page...\n";
list($timeoutPage, $httpCode) = getUrl('http://127.0.0.1:8080/timeout');
if ($httpCode === 200 && strpos($timeoutPage, 'Timeout Test') !== false) {
    echo "   ✓ Timeout page test passed\n";
} else {
    echo "   ✗ Timeout page test failed (HTTP code: $httpCode)\n";
}

// 测试404页面
echo "4. Testing 404 page...\n";
list($notFoundPage, $httpCode) = getUrl('http://127.0.0.1:8080/nonexistent');
if ($httpCode === 404 && strpos($notFoundPage, '404 Not Found') !== false) {
    echo "   ✓ 404 page test passed\n";
} else {
    echo "   ✗ 404 page test failed (HTTP code: $httpCode)\n";
}

echo "All tests completed.\n";