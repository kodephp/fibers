<?php

declare(strict_types=1);

namespace Nova\Fibers\Core;

use Nova\Fibers\Core\EventLoop;

/**
 * 增强的事件循环类
 * 
 * 提供更强大的事件处理功能，包括defer、delay、repeat等
 */
class EnhancedEventLoop extends EventLoop
{
    /**
     * 延迟执行回调（增强版）
     * 
     * 回调在事件循环的下一次迭代中执行。如果有延迟任务调度，事件循环不会在迭代之间等待。
     * 
     * @param callable $callback 回调函数
     * @param string|null $name 任务名称（用于调试）
     * @return string 事件ID
     */
    public static function defer(callable $callback, ?string $name = null): string
    {
        $id = $name ?? uniqid('defer_');
        self::getInstance()->deferQueue[] = [
            'id' => $id,
            'callback' => $callback,
            'created_at' => microtime(true),
            'name' => $name
        ];
        return $id;
    }

    /**
     * 延迟执行回调（增强版）
     * 
     * 回调在指定的秒数后执行。可以使用浮点数表示小数秒。
     * 
     * @param float $delay 延迟秒数
     * @param callable $callback 回调函数
     * @param string|null $name 任务名称（用于调试）
     * @return string 事件ID
     */
    public static function delay(float $delay, callable $callback, ?string $name = null): string
    {
        $id = $name ?? uniqid('delay_');
        $scheduledAt = microtime(true) + $delay;
        
        self::getInstance()->timerQueue[] = [
            'id' => $id,
            'callback' => $callback,
            'scheduled_at' => $scheduledAt,
            'delay' => $delay,
            'name' => $name
        ];
        
        // 保持队列按时间排序
        usort(self::getInstance()->timerQueue, function ($a, $b) {
            return $a['scheduled_at'] <=> $b['scheduled_at'];
        });
        
        return $id;
    }

    /**
     * 重复执行回调（增强版）
     * 
     * 回调在指定的秒数后重复执行。可以使用浮点数表示小数秒。
     * 
     * @param float $interval 间隔秒数
     * @param callable $callback 回调函数
     * @param string|null $name 任务名称（用于调试）
     * @return string 事件ID
     */
    public static function repeat(float $interval, callable $callback, ?string $name = null): string
    {
        $id = $name ?? uniqid('repeat_');
        $scheduledAt = microtime(true) + $interval;
        
        self::getInstance()->repeatQueue[] = [
            'id' => $id,
            'callback' => $callback,
            'scheduled_at' => $scheduledAt,
            'interval' => $interval,
            'name' => $name
        ];
        
        // 保持队列按时间排序
        usort(self::getInstance()->repeatQueue, function ($a, $b) {
            return $a['scheduled_at'] <=> $b['scheduled_at'];
        });
        
        return $id;
    }

    /**
     * 取消事件
     * 
     * @param string $id 事件ID
     * @return bool 是否成功取消
     */
    public static function cancel(string $id): bool
    {
        $instance = self::getInstance();
        
        // 检查延迟队列
        foreach ($instance->deferQueue as $key => $item) {
            if ($item['id'] === $id) {
                unset($instance->deferQueue[$key]);
                return true;
            }
        }
        
        // 检查定时器队列
        foreach ($instance->timerQueue as $key => $item) {
            if ($item['id'] === $id) {
                unset($instance->timerQueue[$key]);
                return true;
            }
        }
        
        // 检查重复定时器队列
        foreach ($instance->repeatQueue as $key => $item) {
            if ($item['id'] === $id) {
                unset($instance->repeatQueue[$key]);
                return true;
            }
        }
        
        return false;
    }

    /**
     * 获取事件循环状态信息
     * 
     * @return array 状态信息
     */
    public static function getStatus(): array
    {
        $instance = self::getInstance();
        
        return [
            'defer_queue_count' => count($instance->deferQueue),
            'timer_queue_count' => count($instance->timerQueue),
            'repeat_queue_count' => count($instance->repeatQueue),
            'stream_queue_count' => count($instance->readStreams) + count($instance->writeStreams),
            'signal_queue_count' => count($instance->signals),
        ];
    }
}