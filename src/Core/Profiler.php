<?php

declare(strict_types=1);

namespace Nova\Fibers\Core;

use Fiber;

/**
 * 性能分析器类
 * 
 * 收集和分析纤程执行性能数据
 */
class Profiler
{
    /**
     * 性能数据
     * 
     * @var array
     */
    protected static array $data = [];

    /**
     * 开始分析
     * 
     * @param string $label 标签
     * @return void
     */
    public static function start(string $label): void
    {
        self::$data[$label] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true),
        ];
    }

    /**
     * 结束分析
     * 
     * @param string $label 标签
     * @return void
     */
    public static function end(string $label): void
    {
        if (isset(self::$data[$label])) {
            self::$data[$label]['end'] = microtime(true);
            self::$data[$label]['memory_end'] = memory_get_usage(true);
            self::$data[$label]['duration'] = self::$data[$label]['end'] - self::$data[$label]['start'];
            self::$data[$label]['memory_used'] = self::$data[$label]['memory_end'] - self::$data[$label]['memory_start'];
        }
    }

    /**
     * 获取分析数据
     * 
     * @return array 分析数据
     */
    public static function getData(): array
    {
        return self::$data;
    }

    /**
     * 重置分析数据
     * 
     * @return void
     */
    public static function reset(): void
    {
        self::$data = [];
    }

    /**
     * 获取格式化的分析报告
     * 
     * @return string 分析报告
     */
    public static function getReport(): string
    {
        $report = "Fiber Profiler Report:\n";
        $report .= str_repeat("-", 50) . "\n";
        
        foreach (self::$data as $label => $data) {
            $report .= sprintf(
                "Label: %s\nDuration: %.4f seconds\nMemory: %d bytes\n\n",
                $label,
                $data['duration'] ?? 0,
                $data['memory_used'] ?? 0
            );
        }
        
        return $report;
    }
}