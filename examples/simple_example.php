<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Channel\Channel;

echo "Kode/Fibers 简单使用示例\n";
echo "========================\n\n";

// 示例1: 基本的纤程使用
echo "1. 基本纤程使用:\n";

// 创建一个简单的纤程
$fiber = new \Fiber(function () {
    echo "  在纤程中执行任务...\n";
    \Fiber::suspend();
    echo "  纤程任务完成!\n";
    return "纤程结果";
});

// 启动纤程
$fiber->start();
echo "  纤程已启动\n";

// 恢复纤程执行
$result = $fiber->resume();
echo "  纤程返回结果: {$result}\n\n";

// 示例2: 使用纤程池
echo "2. 使用纤程池:\n";

// 创建一个小型纤程池
$pool = new FiberPool(['size' => 4]);

// 定义一些任务
$tasks = [
    function() {
        usleep(100000); // 0.1秒
        return "任务1完成";
    },
    function() {
        usleep(200000); // 0.2秒
        return "任务2完成";
    },
    function() {
        usleep(150000); // 0.15秒
        return "任务3完成";
    }
];

// 并发执行任务
$start = microtime(true);
$results = $pool->concurrent($tasks);
$end = microtime(true);

$executionTime = round(($end - $start) * 1000, 2);

echo "  并发执行了 " . count($tasks) . " 个任务\n";
echo "  执行时间: {$executionTime}ms\n";
foreach ($results as $result) {
    echo "  - {$result}\n";
}
echo "\n";

// 示例3: 使用通道进行通信
echo "3. 使用通道进行通信:\n";

// 创建一个通道
$channel = Channel::make('simple-channel', 3);

// 发送数据到通道
$channel->push("消息1");
$channel->push("消息2");
$channel->push("消息3");

echo "  发送了3条消息到通道\n";

// 从通道接收数据
for ($i = 1; $i <= 3; $i++) {
    $message = $channel->pop();
    echo "  接收到消息: {$message}\n";
}

// 关闭通道
$channel->close();
echo "  通道已关闭\n\n";

echo "简单使用示例完成!\n";