<?php

namespace Nova\Fibers\Commands;

/**
 * HelpCommand - 帮助命令
 * 
 * 显示命令行工具的帮助信息
 */
class HelpCommand
{
    /**
     * 运行命令
     *
     * @param array $args 命令行参数
     * @return void
     */
    public function run(array $args): void
    {
        echo "Nova Fibers CLI Tool\n";
        echo "===================\n\n";
        
        echo "Usage:\n";
        echo "  php vendor/bin/fibers <command> [options]\n\n";
        
        echo "Available Commands:\n";
        echo "  init        Initialize configuration files for your framework\n";
        echo "  status      Show current fiber status\n";
        echo "  cleanup     Clean up zombie fibers and release resources\n";
        echo "  benchmark   Run benchmark tests for fiber performance\n";
        echo "  help        Display this help message\n\n";
        
        echo "Options:\n";
        echo "  --concurrency=N  Set concurrency level for benchmark (default: 100)\n";
        echo "  --force          Force overwrite existing configuration files\n\n";
        
        echo "Examples:\n";
        echo "  php vendor/bin/fibers init\n";
        echo "  php vendor/bin/fibers status\n";
        echo "  php vendor/bin/fibers cleanup\n";
        echo "  php vendor/bin/fibers benchmark --concurrency=1000\n";
    }
}