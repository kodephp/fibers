<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * CleanupCommand命令类
 * 
 * 清理僵尸纤程和资源
 */
class CleanupCommand
{
    /**
     * 处理命令
     * 
     * @return void
     */
    public function handle(): void
    {
        echo "Cleaning up...\n";
        
        // 在实际实现中，这里会执行清理操作
        // 例如关闭未正确终止的纤程、释放资源等
        
        // 重置监控数据
        \Nova\Fibers\Core\Monitor::reset();
        
        // 重置性能分析数据
        \Nova\Fibers\Core\Profiler::reset();
        
        echo "Cleanup completed.\n";
    }
}