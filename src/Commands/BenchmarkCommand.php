<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

use Nova\Fibers\Facades\Fiber;
use Nova\Fibers\Core\Profiler;

/**
 * BenchmarkCommand命令类
 * 
 * 性能压测命令
 */
class BenchmarkCommand
{
    /**
     * 处理命令
     * 
     * @param array $options 命令选项
     * @return void
     */
    public function handle(array $options = []): void
    {
        $concurrency = $options['concurrency'] ?? 100;
        $requests = $options['requests'] ?? 1000;
        
        echo "Running benchmark with concurrency={$concurrency}, requests={$requests}\n";
        
        // 开始性能分析
        Profiler::start('benchmark');
        
        // 创建任务
        $tasks = [];
        for ($i = 0; $i < $requests; $i++) {
            $tasks[] = function() {
                // 模拟一些工作
                usleep(10000); // 10ms
                return 'result';
            };
        }
        
        // 并发执行任务
        $results = Fiber::pool(['size' => $concurrency])->concurrent($tasks);
        
        // 结束性能分析
        Profiler::end('benchmark');
        
        // 显示结果
        echo "Benchmark completed.\n";
        echo "Successful requests: " . count($results) . "\n";
        
        // 显示性能分析报告
        echo Profiler::getReport();
    }
}