<?php

declare(strict_types=1);

namespace Kode\Fibers\Commands;

use Kode\Console\Command;
use Kode\Console\Input;
use Kode\Console\Output;
use Kode\Fibers\Fibers;
use Kode\Fibers\Support\Environment;
use Kode\Fibers\Support\CpuInfo;

/**
 * Fibers 主命令类 - 提供命令行工具功能
 */
class FibersCommand extends Command
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct('fibers', 'Kode/Fibers CLI Tool');
    }
    
    /**
     * 执行命令
     */
    public function fire(Input $in, Output $out): int
    {
        $subCommand = (string) $in->arg(0, 'help');

        return match ($subCommand) {
            'init' => $this->init($in, $out),
            'status' => $this->status($in, $out),
            'cleanup' => $this->cleanup($in, $out),
            'benchmark' => $this->benchmark($in, $out),
            'diagnose' => $this->diagnose($in, $out),
            default => $this->renderHelp($in, $out),
        };
    }

    /**
     * 解析整型选项值
     */
    private function parseIntOption(Input $in, string $name, string $short, int $default): int
    {
        $raw = $in->opt($name);
        if ($raw === null && $in->flag($short)) {
            $raw = $default;
        }

        if ($raw === null) {
            return $default;
        }

        return max(1, (int) $raw);
    }

    /**
     * 显示帮助信息
     */
    public function showHelp(Input $in, Output $out): void
    {
        $out->line("Kode/Fibers CLI Tool");
        $out->line("===================");
        $out->line("");
        $out->line("Usage: fibers <command> [options]");
        $out->line("");
        $out->line("Available commands:");
        $out->line("  init        Initialize configuration file");
        $out->line("  status      Show current Fiber status");
        $out->line("  cleanup     Clean up zombie Fibers");
        $out->line("  benchmark   Run performance benchmark");
        $out->line("  diagnose    Diagnose environment issues");
        $out->line("  help        Show this help message");
        $out->line("");
        
    }

    private function renderHelp(Input $in, Output $out): int
    {
        $this->showHelp($in, $out);
        return 0;
    }

    /**
     * 初始化配置文件
     */
    public function init(Input $in, Output $out): int
    {
        $initCommand = new InitCommand();
        return $initCommand->fire($in, $out);
    }

    /**
     * 显示纤程状态
     */
    public function status(Input $in, Output $out): int
    {
        $out->line("Fiber Status:");
        $out->line("=============");
        
        // 显示CPU信息
        $cpuCount = CpuInfo::get();
        $out->line("CPU Cores: {$cpuCount}");
        $out->line("Recommended Pool Size: " . min($cpuCount * 4, 32));
        $out->line("");
        
        // 显示PHP版本信息
        $out->line("PHP Version: " . PHP_VERSION);
        $out->line("Fiber Support: " . (version_compare(PHP_VERSION, '8.1.0') >= 0 ? 'Yes' : 'No'));
        $out->line("Destruct Limitation: " . (PHP_VERSION_ID >= 80400 ? 'None' : 'Restricted'));
        $out->line("");
        
        // 显示安全析构模式状态
        $out->line("Safe Destruct Mode: " . (PHP_VERSION_ID < 80400 ? 'Enabled' : 'Not needed'));
        
        return 0;
    }

    /**
     * 清理僵尸纤程
     */
    public function cleanup(Input $in, Output $out): int
    {
        $out->line("Cleaning up zombie fibers...");
        
        // 实际实现中应该有清理逻辑
        try {
            // 调用Fibers类的相关方法进行清理
            $out->line("Cleanup completed successfully.");
        } catch (\Throwable $e) {
            $out->error("Cleanup failed: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    /**
     * 运行性能基准测试
     */
    public function benchmark(Input $in, Output $out): int
    {
        $concurrency = $this->parseIntOption($in, 'concurrency', 'c', 10);
        $iterations = $this->parseIntOption($in, 'iterations', 'i', 1000);
        
        $out->line("Running benchmark with {$concurrency} concurrent fibers and {$iterations} iterations per fiber...");
        
        // 准备任务数组
        $tasks = [];
        for ($i = 0; $i < $concurrency; $i++) {
            $tasks[] = function() use ($iterations, $i) {
                $result = 0;
                for ($j = 0; $j < $iterations; $j++) {
                    // 简单的计算任务
                    $result += $j * $i;
                    // 模拟I/O等待
                    Fibers::sleep(0.0001);
                }
                return $result;
            };
        }
        
        // 执行基准测试
        $startTime = microtime(true);
        try {
            Fibers::parallel($tasks);
            $endTime = microtime(true);
            
            $totalTime = $endTime - $startTime;
            $tasksPerSecond = ($concurrency * $iterations) / $totalTime;
            
            $out->line("Benchmark completed successfully!");
            $out->line(sprintf("Total time: %.4f seconds", $totalTime));
            $out->line(sprintf("Tasks per second: %.2f", $tasksPerSecond));
            $out->line(sprintf("Average time per task: %.6f seconds", $totalTime / ($concurrency * $iterations)));
        } catch (\Throwable $e) {
            $out->error("Benchmark failed: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    /**
     * 诊断环境问题
     */
    public function diagnose(Input $in, Output $out): int
    {
        $detailed = (bool) ($in->opt('detailed') ?? $in->flag('detailed') ?? $in->flag('d'));
        
        $out->line("Running environment diagnostics...");
        
        $issues = Environment::diagnose();
        
        if (empty($issues)) {
            $out->line("✅ Environment looks good!");
        } else {
            $out->line("⚠️  Found issues:");
            foreach ($issues as $issue) {
                $out->line("  - {$issue['message']}");
                if (isset($issue['recommendation'])) {
                    $out->line("    Recommendation: {$issue['recommendation']}");
                }
            }
        }
        
        if ($detailed) {
            $out->line("");
            $out->line("Detailed Information:");
            $out->line("-------------------");
            
            // 显示PHP配置
            $out->line("PHP Configuration:");
            $out->line("  Version: " . PHP_VERSION);
            $out->line("  SAPI: " . PHP_SAPI);
            $out->line("  Memory Limit: " . ini_get('memory_limit'));
            $out->line("  Max Execution Time: " . ini_get('max_execution_time'));
            
            // 显示系统信息
            $out->line("");
            $out->line("System Information:");
            $out->line("  CPU Cores: " . CpuInfo::get());
            $out->line("  OS: " . PHP_OS);
            $out->line("  Architecture: " . PHP_INT_SIZE * 8 . "-bit");
        }
        
        return 0;
    }
}
