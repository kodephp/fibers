<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Support\CpuInfo;

echo "Kode/Fibers 纤程池演示\n";
echo "=====================\n\n";

// 获取CPU核心数并计算推荐的池大小
$cpuCount = CpuInfo::get();
$recommendedPoolSize = min($cpuCount * 4, 32); // 限制最大池大小

echo "系统信息:\n";
echo "  CPU 核心数: {$cpuCount}\n";
echo "  推荐池大小: {$recommendedPoolSize}\n\n";

// 创建纤程池
$pool = new FiberPool([
    'size' => $recommendedPoolSize,
    'max_exec_time' => 30,
    'gc_interval' => 100
]);

echo "创建纤程池:\n";
echo "  池大小: {$recommendedPoolSize}\n";
echo "  最大执行时间: 30秒\n";
echo "  GC间隔: 100\n\n";

// 模拟一些并发任务
echo "执行并发任务...\n";

// 创建一些模拟任务
$tasks = [];
for ($i = 1; $i <= 10; $i++) {
    $tasks[] = function() use ($i) {
        // 模拟一些工作
        usleep(rand(100000, 500000)); // 0.1-0.5秒
        
        // 返回结果
        return [
            'task_id' => $i,
            'result' => "Task {$i} completed",
            'timestamp' => date('Y-m-d H:i:s')
        ];
    };
}

// 执行并发任务
$start = microtime(true);
$results = $pool->concurrent($tasks);
$end = microtime(true);

$executionTime = round(($end - $start) * 1000, 2);

echo "任务执行完成!\n";
echo "  执行时间: {$executionTime}ms\n";
echo "  完成任务数: " . count($results) . "\n\n";

// 显示结果
echo "任务结果:\n";
foreach ($results as $index => $result) {
    echo "  [{$result['task_id']}] {$result['result']} at {$result['timestamp']}\n";
}

echo "\n池状态信息:\n";
echo "  活跃纤程数: " . $pool->getActiveCount() . "\n";
echo "  总执行任务数: " . $pool->getTotalExecuted() . "\n";

echo "\n演示完成!\n";