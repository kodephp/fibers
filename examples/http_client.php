<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Facades\Fiber;

/**
 * HTTP客户端示例
 * 
 * 此脚本演示了如何使用 nova/fibers 包进行并发HTTP请求
 */

echo "Nova Fibers HTTP Client Example\n";
echo str_repeat("=", 50) . "\n";

// 定义要请求的URL列表
$urls = [
    'https://httpbin.org/delay/1',
    'https://httpbin.org/delay/2',
    'https://httpbin.org/delay/1',
    'https://httpbin.org/delay/3',
    'https://httpbin.org/delay/1',
];

echo "Making concurrent HTTP requests to " . count($urls) . " URLs...\n";

// 使用纤程池并发执行HTTP请求
$start = microtime(true);

$pool = new FiberPool(['size' => 10]);

$tasks = array_map(function ($url) {
    return function () use ($url) {
        // 模拟HTTP请求（在实际应用中，这里会是真正的HTTP客户端调用）
        echo "Starting request to: {$url}\n";
        
        // 模拟网络延迟
        usleep(rand(500000, 2000000)); // 0.5-2秒
        
        // 模拟响应
        $response = [
            'url' => $url,
            'status' => 200,
            'time' => date('Y-m-d H:i:s'),
            'data' => 'Response from ' . parse_url($url, PHP_URL_HOST)
        ];
        
        echo "Completed request to: {$url}\n";
        return $response;
    };
}, $urls);

try {
    $results = $pool->concurrent($tasks);
    
    $elapsed = microtime(true) - $start;
    
    echo "\nAll requests completed in " . number_format($elapsed, 2) . " seconds\n";
    echo "Results:\n";
    
    foreach ($results as $i => $result) {
        echo "  " . ($i + 1) . ". {$result['url']} - Status: {$result['status']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "HTTP Client Example Completed!\n";