<?php

declare(strict_types=1);

namespace Nova\Fibers\Profiler;

/**
 * Fiber性能分析器
 *
 * 收集和分析Fiber运行信息
 */
class FiberProfiler
{
    /**
     * @var array<string, array> Fiber统计信息
     */
    private static array $fiberStats = [];

    /**
     * @var array<string, float> Fiber开始时间
     */
    private static array $fiberStartTimes = [];

    /**
     * @var bool 是否启用分析器
     */
    private static bool $enabled = false;

    /**
     * 启用分析器
     *
     * @return void
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * 禁用分析器
     *
     * @return void
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * 检查分析器是否启用
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * 记录Fiber开始
     *
     * @param string $fiberId Fiber ID
     * @param string $name Fiber名称
     * @return void
     */
    public static function startFiber(string $fiberId, string $name = 'unnamed'): void
    {
        if (!self::$enabled) {
            return;
        }

        self::$fiberStartTimes[$fiberId] = microtime(true);
        self::$fiberStats[$fiberId] = [
            'name' => $name,
            'start_time' => self::$fiberStartTimes[$fiberId],
            'end_time' => null,
            'duration' => null,
            'status' => 'running'
        ];
    }

    /**
     * 记录Fiber结束
     *
     * @param string $fiberId Fiber ID
     * @param string $status 状态
     * @return void
     */
    public static function endFiber(string $fiberId, string $status = 'completed'): void
    {
        if (!self::$enabled) {
            return;
        }

        if (!isset(self::$fiberStartTimes[$fiberId])) {
            return;
        }

        $endTime = microtime(true);
        $duration = $endTime - self::$fiberStartTimes[$fiberId];

        self::$fiberStats[$fiberId]['end_time'] = $endTime;
        self::$fiberStats[$fiberId]['duration'] = $duration;
        self::$fiberStats[$fiberId]['status'] = $status;

        unset(self::$fiberStartTimes[$fiberId]);
    }

    /**
     * 获取Fiber统计信息
     *
     * @param string|null $fiberId Fiber ID，如果为null则返回所有统计信息
     * @return array
     */
    public static function getStats(?string $fiberId = null): array
    {
        if ($fiberId !== null) {
            return self::$fiberStats[$fiberId] ?? [];
        }

        return self::$fiberStats;
    }

    /**
     * 获取分析报告
     *
     * @return array
     */
    public static function getReport(): array
    {
        if (!self::$enabled) {
            return [];
        }

        $totalFibers = count(self::$fiberStats);
        $completedFibers = 0;
        $failedFibers = 0;
        $runningFibers = 0;
        $totalDuration = 0;
        $maxDuration = 0;
        $minDuration = PHP_FLOAT_MAX;

        foreach (self::$fiberStats as $stat) {
            if ($stat['status'] === 'completed') {
                $completedFibers++;
                $totalDuration += $stat['duration'];
                $maxDuration = max($maxDuration, $stat['duration']);
                $minDuration = min($minDuration, $stat['duration']);
            } elseif ($stat['status'] === 'failed') {
                $failedFibers++;
            } else {
                $runningFibers++;
            }
        }

        return [
            'total_fibers' => $totalFibers,
            'completed_fibers' => $completedFibers,
            'failed_fibers' => $failedFibers,
            'running_fibers' => $runningFibers,
            'total_duration' => $totalDuration,
            'average_duration' => $completedFibers > 0 ? $totalDuration / $completedFibers : 0,
            'max_duration' => $maxDuration,
            'min_duration' => $minDuration === PHP_FLOAT_MAX ? 0 : $minDuration
        ];
    }

    /**
     * 重置统计信息
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$fiberStats = [];
        self::$fiberStartTimes = [];
    }
}
