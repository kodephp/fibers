<?php

namespace Nova\Fibers\Commands;

use Nova\Fibers\FiberManager;
use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Support\CpuInfo;

/**
 * BenchmarkCommand - 性能压测命令
 * 
 * 用于测试纤程池的最大吞吐量和性能表现
 */
class BenchmarkCommand extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('benchmark')
             ->setDescription('Run performance benchmark tests')
             ->setHelp('This command runs performance benchmark tests to measure fiber throughput.')
             ->addOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'Concurrency level', 100)
             ->addOption('requests', 'r', InputOption::VALUE_REQUIRED, 'Number of requests', 1000)
             ->addOption('pool-size', 'p', InputOption::VALUE_REQUIRED, 'Fiber pool size', CpuInfo::get() * 4);
    }

    /**
     * 执行命令
     *
     * @param InputInterface $input 输入接口
     * @param OutputInterface $output 输出接口
     * @return int 命令执行结果
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $concurrency = (int) $input->getOption('concurrency');
        $requests = (int) $input->getOption('requests');
        $poolSize = (int) $input->getOption('pool-size');
        
        $output->writeln("<info>Running Nova Fibers benchmark...</info>");
        $output->writeln("Concurrency: {$concurrency}");
        $output->writeln("Requests: {$requests}");
        $output->writeln("Pool Size: {$poolSize}");
        $output->writeln("");
        
        // 创建纤程池
        $pool = new FiberPool(['size' => $poolSize]);
        
        // 运行基准测试
        $this->runBenchmark($pool, $concurrency, $requests, $output);
        
        return self::SUCCESS;
    }

    /**
     * 运行基准测试
     *
     * @param FiberPool $pool 纤程池实例
     * @param int $concurrency 并发数
     * @param int $requests 请求总数
     * @param OutputInterface $output 输出接口
     * @return void
     */
    private function runBenchmark(FiberPool $pool, int $concurrency, int $requests, OutputInterface $output): void
    {
        $output->writeln("Starting benchmark...");
        
        // 记录开始时间
        $startTime = microtime(true);
        
        // 创建测试任务
        $tasks = [];
        for ($i = 0; $i < $requests; $i++) {
            $tasks[] = function() {
                // 模拟一个简单的任务
                usleep(1000); // 1ms
                return "Task completed";
            };
        }
        
        // 分批执行任务
        $batches = array_chunk($tasks, $concurrency);
        $results = [];
        
        foreach ($batches as $batch) {
            $batchResults = $pool->concurrent($batch);
            $results = array_merge($results, $batchResults);
        }
        
        // 记录结束时间
        $endTime = microtime(true);
        
        // 计算统计信息
        $totalTime = $endTime - $startTime;
        $requestsPerSecond = $requests / $totalTime;
        
        // 显示结果
        $output->writeln("\n<info>Benchmark Results:</info>");
        $output->writeln("Total time: " . number_format($totalTime, 4) . " seconds");
        $output->writeln("Requests per second: " . number_format($requestsPerSecond, 2));
        $output->writeln("Total requests: {$requests}");
        $output->writeln("Successful requests: " . count($results));
    }
}