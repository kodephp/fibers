<?php

require_once 'vendor/autoload.php';

use Nova\Fibers\Core\FiberPool;

echo "开始执行任务，设置超时为1毫秒...\n";
$start = microtime(true);

try {
    $pool = new FiberPool(['size' => 2]);
    
    $tasks = [
        fn() => usleep(10000) ?: 'done', // 10ms task
    ];
    
    // 设置超时为1毫秒，任务需要10毫秒，应该抛出异常
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