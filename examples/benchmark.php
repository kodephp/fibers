<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Facades\Fiber;
use Nova\Fibers\Support\CpuInfo;

// 设置错误报告级别
error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * 纤程库基准测试脚本
 * 
 * 此脚本演示了如何使用 nova/fibers 包进行并发任务处理
 */

echo "Nova Fibers Benchmark\n";
echo str_repeat("=", 50) . "\n";

// 获取CPU核心数
$cpuCount = CpuInfo::get();
echo "CPU Cores: {$cpuCount}\n";

// 测试一键纤程
echo "\n1. Testing Fiber::run() - Simple concurrent tasks\n";
$start = microtime(true);

$results = [];
for ($i = 0; $i < 10; $i++) {
    $results[] = Fiber::run(function () use ($i) {
        // 模拟一些工作
        usleep(100000); // 0.1秒
        return "Task {$i} completed";
    });
}

$elapsed = microtime(true) - $start;
echo "Completed 10 tasks in " . number_format($elapsed, 4) . " seconds\n";
echo "Results: " . implode(", ", array_slice($results, 0, 3)) . "...\n";

// 测试纤程池
echo "\n2. Testing FiberPool - High concurrency\n";
$start = microtime(true);

$pool = new FiberPool([
    'size' => $cpuCount * 4,
    'name' => 'benchmark-pool'
]);

// 创建100个任务
$tasks = [];
for ($i = 0; $i < 100; $i++) {
    $tasks[] = function () use ($i) {
        // 模拟一些工作
        usleep(50000); // 0.05秒
        return $i * 2;
    };
}

$results = $pool->concurrent($tasks);
$elapsed = microtime(true) - $start;

echo "Completed 100 tasks in " . number_format($elapsed, 4) . " seconds\n";
echo "Average time per task: " . number_format(($elapsed / 100) * 1000, 2) . " ms\n";
echo "Sample results: " . implode(", ", array_slice($results, 0, 10)) . "...\n";

// 测试通道通信
echo "\n3. Testing Channel communication\n";
$start = microtime(true);

try {
    // 创建通道
    $channel = \Nova\Fibers\Channel\Channel::make('benchmark', 10);

    // 启动生产者纤程
    $producer = new \Fiber(function () use ($channel) {
        for ($i = 0; $i < 50; $i++) {
            $channel->push("Message {$i}");
            usleep(10000); // 0.01秒
        }
        $channel->close();
    });
    $producer->start();

    // 消费者循环
    $messages = [];
    while (($message = $channel->pop(1)) !== null) {
        $messages[] = $message;
    }

    // 等待生产者完成
    while (!$producer->isTerminated()) {
        usleep(1000);
    }

    $elapsed = microtime(true) - $start;
    echo "Produced and consumed 50 messages in " . number_format($elapsed, 4) . " seconds\n";
    echo "Messages received: " . count($messages) . "\n";
} catch (Exception $e) {
    echo "Channel test failed: " . $e->getMessage() . "\n";
}

// 测试事件总线
echo "\n4. Testing EventBus\n";

// 定义事件类
class BenchmarkEvent {
    public function __construct(public int $id) {}
}

try {
    $eventCount = 0;
    \Nova\Fibers\Event\EventBus::on(BenchmarkEvent::class, function (BenchmarkEvent $event) use (&$eventCount) {
        $eventCount++;
    });

    $start = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        \Nova\Fibers\Event\EventBus::fire(new BenchmarkEvent($i));
    }
    $elapsed = microtime(true) - $start;

    echo "Fired 1000 events in " . number_format($elapsed, 4) . " seconds\n";
    echo "Events processed: {$eventCount}\n";
} catch (Exception $e) {
    echo "EventBus test failed: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Benchmark completed!\n";