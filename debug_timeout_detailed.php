<?php

require_once 'vendor/autoload.php';

use Nova\Fibers\Core\FiberPool;

echo "=== 测试1: 设置0.001秒超时执行可中断任务 ===\n";
$start = microtime(true);

try {
    $pool = new FiberPool(['size' => 2]);
    
    // 创建一个可以响应中断的任务
    $tasks = [
        function() {
            // 模拟一个长时间运行的任务，但会定期检查是否应该停止
            for ($i = 0; $i < 100; $i++) {
                usleep(100); // 每次休眠100微秒
            }
            return 'done';
        }
    ];
    
    // 设置超时为0.001秒(1ms)，任务需要10ms(100*100微秒)
    $results = $pool->concurrent($tasks, 0.001);
    
    $end = microtime(true);
    echo "任务完成，耗时: " . ($end - $start) . " 秒\n";
    var_dump($results);
} catch (\RuntimeException $e) {
    $end = microtime(true);
    echo "捕获到RuntimeException异常\n";
    echo "耗时: " . ($end - $start) . " 秒\n";
    echo "异常消息: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    $end = microtime(true);
    echo "捕获到Exception异常\n";
    echo "耗时: " . ($end - $start) . " 秒\n";
    echo "异常消息: " . $e->getMessage() . "\n";
} catch (\Throwable $e) {
    $end = microtime(true);
    echo "捕获到Throwable异常\n";
    echo "耗时: " . ($end - $start) . " 秒\n";
    echo "异常消息: " . $e->getMessage() . "\n";
}

echo "\n=== 测试2: 无超时情况 ===\n";
$start = microtime(true);

try {
    $pool = new FiberPool(['size' => 2]);
    
    $tasks = [
        function() {
            // 模拟一个快速任务
            for ($i = 0; $i < 10; $i++) {
                usleep(100); // 每次休眠100微秒
            }
            return 'quick_task';
        }
    ];
    
    // 不设置超时
    $results = $pool->concurrent($tasks);
    
    $end = microtime(true);
    echo "任务完成，耗时: " . ($end - $start) . " 秒\n";
    var_dump($results);
} catch (\RuntimeException $e) {
    $end = microtime(true);
    echo "捕获到RuntimeException异常\n";
    echo "耗时: " . ($end - $start) . " 秒\n";
    echo "异常消息: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    $end = microtime(true);
    echo "捕获到Exception异常\n";
    echo "耗时: " . ($end - $start) . " 秒\n";
    echo "异常消息: " . $e->getMessage() . "\n";
} catch (\Throwable $e) {
    $end = microtime(true);
    echo "捕获到Throwable异常\n";
    echo "耗时: " . ($end - $start) . " 秒\n";
    echo "异常消息: " . $e->getMessage() . "\n";
}