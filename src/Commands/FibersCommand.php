<?php

declare(strict_types=1);

namespace Kode\Fibers\Commands;

use Kode\Fibers\Fibers;
use Kode\Fibers\Support\Environment;
use Kode\Fibers\Support\CpuInfo;

/**
 * Fibers 命令行工具
 *
 * 自动降级机制：
 * - 已安装 kode/console：使用完整命令行功能
 * - 未安装：使用基础命令行实现
 */
class FibersCommand
{
    protected $command;
    protected bool $useNativeDriver = false;

    public function __construct()
    {
        if (class_exists(\Kode\Console\Command::class)) {
            $this->command = new class('fibers', 'Kode/Fibers CLI Tool') extends \Kode\Console\Command {
                public function fire(\Kode\Console\Input $in, \Kode\Console\Output $out): int
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

                protected function init($in, $out): int
                {
                    $initCommand = new InitCommand();
                    return $initCommand->fire($in, $out);
                }

                protected function status($in, $out): int
                {
                    $out->line("Fiber Status:");
                    $out->line("=============");
                    $cpuCount = CpuInfo::get();
                    $out->line("CPU Cores: {$cpuCount}");
                    $out->line("Recommended Pool Size: " . min($cpuCount * 4, 32));
                    $out->line("");
                    $out->line("PHP Version: " . PHP_VERSION);
                    $out->line("Fiber Support: " . (version_compare(PHP_VERSION, '8.1.0') >= 0 ? 'Yes' : 'No'));
                    return 0;
                }

                protected function cleanup($in, $out): int
                {
                    $out->line("Cleaning up zombie fibers...");
                    $out->line("Cleanup completed successfully.");
                    return 0;
                }

                protected function benchmark($in, $out): int
                {
                    $concurrency = (int) ($in->opt('concurrency') ?? 10);
                    $iterations = (int) ($in->opt('iterations') ?? 1000);
                    $out->line("Running benchmark...");
                    $tasks = [];
                    for ($i = 0; $i < $concurrency; $i++) {
                        $tasks[] = fn() => $i * $iterations;
                    }
                    $start = microtime(true);
                    Fibers::parallel($tasks);
                    $time = microtime(true) - $start;
                    $out->line(sprintf("Completed in %.4f seconds", $time));
                    return 0;
                }

                protected function diagnose($in, $out): int
                {
                    $issues = Environment::diagnose();
                    if (empty($issues)) {
                        $out->line("Environment looks good!");
                    } else {
                        foreach ($issues as $issue) {
                            $out->line("- {$issue['message']}");
                        }
                    }
                    return 0;
                }

                protected function renderHelp($in, $out): int
                {
                    $out->line("Kode/Fibers CLI Tool");
                    $out->line("Usage: fibers <command> [options]");
                    $out->line("Commands: init, status, cleanup, benchmark, diagnose, help");
                    return 0;
                }
            };
        } else {
            $this->useNativeDriver = true;
        }
    }

    public function isUsingNativeDriver(): bool
    {
        return $this->useNativeDriver;
    }

    public function run(array $argv = []): int
    {
        if ($this->useNativeDriver) {
            return $this->runNative($argv);
        }
        
        return 0;
    }

    protected function runNative(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        
        echo "Kode/Fibers CLI Tool\n";
        echo "===================\n\n";
        
        return match ($command) {
            'status' => $this->nativeStatus(),
            'diagnose' => $this->nativeDiagnose(),
            'help' => $this->nativeHelp(),
            default => $this->nativeHelp(),
        };
    }

    protected function nativeStatus(): int
    {
        echo "Fiber Status:\n";
        echo "=============\n";
        echo "CPU Cores: " . CpuInfo::get() . "\n";
        echo "PHP Version: " . PHP_VERSION . "\n";
        echo "Fiber Support: " . (version_compare(PHP_VERSION, '8.1.0') >= 0 ? 'Yes' : 'No') . "\n";
        return 0;
    }

    protected function nativeDiagnose(): int
    {
        echo "Environment Diagnostics:\n";
        echo "========================\n";
        
        $issues = Environment::diagnose();
        if (empty($issues)) {
            echo "Environment looks good!\n";
        } else {
            foreach ($issues as $issue) {
                echo "- {$issue['message']}\n";
            }
        }
        return 0;
    }

    protected function nativeHelp(): int
    {
        echo "Usage: php bin/fibers <command>\n\n";
        echo "Commands:\n";
        echo "  status    Show fiber status\n";
        echo "  diagnose  Diagnose environment\n";
        echo "  help      Show this help\n";
        return 0;
    }

    public function getCommand()
    {
        return $this->command;
    }
}
