<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Facades\Fiber;
use Nova\Fibers\Core\FiberPool;

echo "=== Nova Fibers HTTP 客户端示例 ===\n\n";

// 创建一个纤程池用于并发请求
$pool = new FiberPool(['size' => 5]);

// 定义要请求的 URLs
$urls = [
    'https://httpbin.org/delay/1',
    'https://httpbin.org/delay/2',
    'https://httpbin.org/get',
    'https://httpbin.org/user-agent',
    'https://httpbin.org/headers'
];

echo "并发请求 " . count($urls) . " 个 URLs...\n";

// 使用纤程池并发执行 HTTP 请求
$start = microtime(true);
$results = $pool->concurrent(array_map(function($url) {
    return function() use ($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
                'header' => [
                    'User-Agent: Nova-Fibers-Client/1.0'
                ]
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        return [
            'url' => $url,
            'status' => $http_response_header[0] ?? 'Unknown',
            'length' => strlen($response)
        ];
    };
}, $urls));

$elapsed = microtime(true) - $start;

echo "\n请求完成，耗时: " . number_format($elapsed, 3) . " 秒\n\n";

// 显示结果
foreach ($results as $index => $result) {
    echo "请求 " . ($index + 1) . ":\n";
    echo "  URL: {$result['url']}\n";
    echo "  状态: {$result['status']}\n";
    echo "  响应长度: {$result['length']} 字节\n\n";
}

echo "=== 示例完成 ===\n";