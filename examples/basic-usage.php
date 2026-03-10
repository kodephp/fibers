<?php

/**
 * Kode/Fibers - 基础使用示例
 * 
 * 这个示例展示了Kode/Fibers包的基础使用方法，包括：
 * - 如何创建和运行简单的纤程任务
 * - 如何使用超时控制
 * - 如何处理纤程中的异常
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Fibers\Fibers;
use Kode\Fibers\Exceptions\FiberException;

// 示例1: 基本纤程任务执行
function basicFiber() {
    echo "\n--- 示例1: 基本纤程任务执行 ---", PHP_EOL;
    
    // 创建并运行一个简单的纤程任务
    $result = Fibers::run(function() {
        echo "正在执行纤程任务...", PHP_EOL;
        Fibers::sleep(0.5); // 非阻塞睡眠
        return "纤程任务完成！";
    });
    
    echo "纤程返回结果: ", $result, PHP_EOL;
}

// 示例2: 带超时的纤程任务
function fiberWithTimeout() {
    echo "\n--- 示例2: 带超时的纤程任务 ---", PHP_EOL;
    
    try {
        // 执行一个会超时的任务
        $result = Fibers::run(function() {
            echo "执行可能超时的任务...", PHP_EOL;
            Fibers::sleep(2); // 睡眠2秒
            return "这个结果不会被看到";
        }, 1); // 超时设置为1秒
        
        echo "结果: ", $result, PHP_EOL; // 不会执行到这里
    } catch (RuntimeException $e) {
        echo "捕获到超时异常: ", $e->getMessage(), PHP_EOL;
    }
}

// 示例3: 异常处理
function fiberWithException() {
    echo "\n--- 示例3: 纤程中的异常处理 ---", PHP_EOL;
    
    try {
        $result = Fibers::run(function() {
            echo "执行可能抛出异常的任务...", PHP_EOL;
            throw new Exception("纤程内部异常");
            return "这个结果不会被返回";
        });
        
        echo "结果: ", $result, PHP_EOL; // 不会执行到这里
    } catch (Exception $e) {
        echo "捕获到纤程异常: ", $e->getMessage(), PHP_EOL;
    }
}

// 示例4: 简单的并行任务
function simpleParallelTasks() {
    echo "\n--- 示例4: 简单的并行任务 ---", PHP_EOL;
    
    $startTime = microtime(true);
    
    // 创建多个任务
    $tasks = [
        function() {
            Fibers::sleep(1);
            return "任务1完成";
        },
        function() {
            Fibers::sleep(1);
            return "任务2完成";
        },
        function() {
            Fibers::sleep(1);
            return "任务3完成";
        }
    ];
    
    // 并行执行所有任务
    $results = Fibers::parallel($tasks);
    
    $endTime = microtime(true);
    
    echo "所有任务完成时间: ", round($endTime - $startTime, 4), "秒", PHP_EOL;
    echo "任务结果:", PHP_EOL;
    foreach ($results as $index => $result) {
        echo "  任务", $index + 1, ": ", $result, PHP_EOL;
    }
}

// 运行所有示例
echo "====== Kode/Fibers 基础使用示例 ======", PHP_EOL;
basicFiber();
fiberWithTimeout();
fiberWithException();
simpleParallelTasks();
echo "\n====== 示例执行完毕 ======", PHP_EOL;