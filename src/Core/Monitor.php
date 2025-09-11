<?php

declare(strict_types=1);

namespace Nova\Fibers\Core;

use Fiber;

/**
 * 监控器类
 * 
 * 监控纤程池和通道的状态
 */
class Monitor
{
    /**
     * 监控数据
     * 
     * @var array
     */
    protected static array $data = [
        'fiber_count' => 0,
        'active_fibers' => 0,
        'terminated_fibers' => 0,
        'channel_stats' => [],
    ];

    /**
     * 更新纤程计数
     * 
     * @param int $count 纤程数量变化
     * @return void
     */
    public static function updateFiberCount(int $count): void
    {
        self::$data['fiber_count'] += $count;
        if ($count > 0) {
            self::$data['active_fibers'] += $count;
        }
    }

    /**
     * 标记纤程为活跃状态
     * 
     * @return void
     */
    public static function markFiberActive(): void
    {
        self::$data['active_fibers']++;
    }

    /**
     * 标记纤程为终止状态
     * 
     * @return void
     */
    public static function markFiberTerminated(): void
    {
        self::$data['active_fibers']--;
        self::$data['terminated_fibers']++;
    }

    /**
     * 更新通道统计信息
     * 
     * @param string $channelName 通道名称
     * @param int $bufferSize 缓冲区大小
     * @param int $currentCount 当前元素数量
     * @return void
     */
    public static function updateChannelStats(string $channelName, int $bufferSize, int $currentCount): void
    {
        self::$data['channel_stats'][$channelName] = [
            'buffer_size' => $bufferSize,
            'current_count' => $currentCount,
        ];
    }

    /**
     * 获取监控数据
     * 
     * @return array 监控数据
     */
    public static function getData(): array
    {
        return self::$data;
    }

    /**
     * 获取格式化的监控报告
     * 
     * @return string 监控报告
     */
    public static function getReport(): string
    {
        $report = "Fiber Monitor Report:\n";
        $report .= str_repeat("-", 50) . "\n";
        $report .= sprintf("Total Fibers: %d\n", self::$data['fiber_count']);
        $report .= sprintf("Active Fibers: %d\n", self::$data['active_fibers']);
        $report .= sprintf("Terminated Fibers: %d\n", self::$data['terminated_fibers']);
        $report .= "\nChannel Stats:\n";
        
        foreach (self::$data['channel_stats'] as $name => $stats) {
            $report .= sprintf(
                "  %s: buffer=%d, current=%d\n",
                $name,
                $stats['buffer_size'],
                $stats['current_count']
            );
        }
        
        return $report;
    }
}