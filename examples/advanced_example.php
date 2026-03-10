<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Fibers\Core\FiberPool;
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Support\CpuInfo;

echo "Kode/Fibers 高级功能演示\n";
echo "========================\n\n";

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

// 创建通道用于结果收集
$resultChannel = Channel::make('results', 10);

echo "创建结果通道:\n";
echo "  通道名称: results\n";
echo "  缓冲区大小: 10\n\n";

// 模拟一些并发任务
echo "启动并发任务处理...\n";

// 创建生产者任务 - 生成需要处理的数据
$producerTasks = [];
for ($i = 1; $i <= 5; $i++) {
    $producerTasks[] = function() use ($i, $resultChannel) {
        // 模拟生成数据
        usleep(rand(100000, 300000)); // 0.1-0.3秒
        
        // 发送数据到处理通道
        $data = [
            'id' => $i,
            'value' => rand(1, 100),
            'timestamp' => microtime(true)
        ];
        
        echo "  [生产者 {$i}] 生成数据: ID={$data['id']}, Value={$data['value']}\n";
        return $data;
    };
}

// 并发执行生产者任务
$producedData = $pool->concurrent($producerTasks);

// 创建处理任务 - 处理生成的数据
$processorTasks = [];
foreach ($producedData as $data) {
    $processorTasks[] = function() use ($data, $resultChannel) {
        // 模拟处理数据
        usleep(rand(200000, 500000)); // 0.2-0.5秒
        
        // 处理数据
        $processed = [
            'original_id' => $data['id'],
            'processed_value' => $data['value'] * 2,
            'processing_time' => round((microtime(true) - $data['timestamp']) * 1000, 2) . 'ms'
        ];
        
        echo "  [处理器 {$data['id']}] 处理完成: 原始值={$data['value']}, 处理后={$processed['processed_value']}\n";
        
        // 将结果发送到结果通道
        $resultChannel->push($processed);
        
        return $processed;
    };
}

// 启动处理器任务
$start = microtime(true);
$processingResults = $pool->concurrent($processorTasks);
$end = microtime(true);

$processingTime = round(($end - $start) * 1000, 2);

echo "\n处理任务完成!\n";
echo "  处理时间: {$processingTime}ms\n";
echo "  处理任务数: " . count($processingResults) . "\n\n";

// 收集结果
echo "收集处理结果:\n";
$results = [];
while ($resultChannel->length() > 0) {
    $result = $resultChannel->pop(0.1); // 0.1秒超时
    if ($result !== null) {
        $results[] = $result;
        echo "  收集到结果: ID={$result['original_id']}, 处理值={$result['processed_value']}\n";
    }
}

// 关闭通道
$resultChannel->close();

echo "\n最终结果汇总:\n";
foreach ($results as $result) {
    echo "  ID {$result['original_id']}: {$result['processed_value']} (耗时: {$result['processing_time']})\n";
}

echo "\n池状态信息:\n";
echo "  活跃纤程数: " . $pool->getActiveCount() . "\n";
echo "  总执行任务数: " . $pool->getTotalExecuted() . "\n";

echo "\n高级功能演示完成!\n";