<?php

/**
 * 重试功能示例
 * 
 * 此示例演示了如何在纤程池中使用重试功能
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Fibers\Facades\Fiber;
use Kode\Fibers\Core\FiberPool;

// 创建一个可能失败的任务
function unreliableTask($shouldFail = true) {
    static $attempt = 0;
    $attempt++;
    
    echo "Task execution attempt: $attempt\n";
    
    // 模拟一个可能失败的任务
    if ($shouldFail && $attempt < 3) {
        throw new Exception("Task failed on attempt $attempt");
    }
    
    return "Task succeeded on attempt $attempt";
}

echo "=== 单个任务重试示例 ===\n";

try {
    // 创建一个带有重试配置的纤程池
    $pool = new FiberPool([
        'max_retries' => 5,  // 最多重试5次
        'retry_delay' => 0.5 // 重试延迟0.5秒
    ]);
    
    // 运行可能失败的任务
    $result = $pool->run(function() {
        return unreliableTask(true);
    });
    
    echo "Task result: $result\n";
} catch (Exception $e) {
    echo "Task failed after all retries: " . $e->getMessage() . "\n";
}

echo "\n=== 并行任务重试示例 ===\n";

// 创建多个可能失败的任务
$tasks = [
    function() { return unreliableTask(true); },
    function() { return unreliableTask(false); }, // 这个任务不会失败
    function() { 
        static $attempt = 0;
        $attempt++;
        if ($attempt < 2) {
            throw new Exception("Parallel task failed on attempt $attempt");
        }
        return "Parallel task succeeded on attempt $attempt";
    }
];

try {
    // 创建一个带有重试配置的纤程池
    $pool = new FiberPool([
        'max_retries' => 3,  // 最多重试3次
        'retry_delay' => 0.2 // 重试延迟0.2秒
    ]);
    
    // 并行运行任务
    $results = $pool->concurrent($tasks);
    
    foreach ($results as $key => $result) {
        if ($result instanceof Exception) {
            echo "Task $key failed: " . $result->getMessage() . "\n";
        } else {
            echo "Task $key result: $result\n";
        }
    }
} catch (Exception $e) {
    echo "Parallel execution failed: " . $e->getMessage() . "\n";
}

echo "\n=== 使用Fiber门面的重试示例 ===\n";

try {
    // 使用Fiber门面运行任务
    $result = Fiber::run(function() {
        return unreliableTask(true);
    });
    
    echo "Fiber facade result: $result\n";
} catch (Exception $e) {
    echo "Fiber facade task failed: " . $e->getMessage() . "\n";
}