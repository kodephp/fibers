<?php

declare(strict_types=1);

namespace Nova\Fibers\Event;

use Nova\Fibers\Event\EventBus;
use Nova\Fibers\Core\FiberPool;

/**
 * 增强的事件总线类
 * 
 * 提供更强大的发布/订阅功能
 */
class EnhancedEventBus extends EventBus
{
    /**
     * 事件处理器权重
     * 
     * @var array
     */
    private static array $handlerWeights = [];

    /**
     * 事件历史记录
     * 
     * @var array
     */
    private static array $eventHistory = [];

    /**
     * 最大历史记录数
     * 
     * @var int
     */
    private static int $maxHistory = 1000;

    /**
     * 订阅事件（支持权重）
     * 
     * @param string $event 事件名称
     * @param callable $callback 回调函数
     * @param int $weight 权重（越大越优先）
     * @return string 订阅ID
     */
    public static function on(string $event, callable $callback, int $weight = 0): string
    {
        $id = parent::on($event, $callback);
        
        // 存储权重信息
        self::$handlerWeights[$event][$id] = $weight;
        
        // 按权重排序处理器
        if (isset(self::$handlers[$event])) {
            uksort(self::$handlers[$event], function ($a, $b) use ($event) {
                $weightA = self::$handlerWeights[$event][$a] ?? 0;
                $weightB = self::$handlerWeights[$event][$b] ?? 0;
                return $weightB <=> $weightA; // 权重高的在前
            });
        }
        
        return $id;
    }

    /**
     * 发布事件（增强版）
     * 
     * @param object $event 事件对象
     * @param bool $recordHistory 是否记录历史
     * @param FiberPool|null $fiberPool 纤程池
     * @return void
     */
    public static function fire(object $event, bool $recordHistory = true, ?FiberPool $fiberPool = null): void
    {
        $eventName = get_class($event);
        
        // 记录事件历史
        if ($recordHistory) {
            self::recordEvent($eventName, $event);
        }
        
        // 如果有纤程池，则并发处理事件
        if ($fiberPool && isset(self::$handlers[$eventName])) {
            $tasks = [];
            foreach (self::$handlers[$eventName] as $id => $callback) {
                $tasks[] = function () use ($callback, $event) {
                    return $callback($event);
                };
            }
            
            // 并发执行所有处理器
            $fiberPool->concurrent($tasks);
        } else {
            // 同步处理事件
            parent::fire($event);
        }
    }

    /**
     * 记录事件
     * 
     * @param string $eventName 事件名称
     * @param object $event 事件对象
     * @return void
     */
    private static function recordEvent(string $eventName, object $event): void
    {
        self::$eventHistory[] = [
            'event' => $eventName,
            'data' => json_encode($event),
            'timestamp' => microtime(true),
        ];
        
        // 限制历史记录数量
        if (count(self::$eventHistory) > self::$maxHistory) {
            array_shift(self::$eventHistory);
        }
    }

    /**
     * 获取事件历史
     * 
     * @param string|null $event 事件名称（可选）
     * @param int $limit 限制数量
     * @return array 事件历史
     */
    public static function getEventHistory(?string $event = null, int $limit = 50): array
    {
        $history = self::$eventHistory;
        
        // 过滤特定事件
        if ($event) {
            $history = array_filter($history, function ($item) use ($event) {
                return $item['event'] === $event;
            });
        }
        
        // 限制数量
        return array_slice($history, -$limit);
    }

    /**
     * 清除事件历史
     * 
     * @return void
     */
    public static function clearEventHistory(): void
    {
        self::$eventHistory = [];
    }

    /**
     * 获取事件统计信息
     * 
     * @return array 统计信息
     */
    public static function getStats(): array
    {
        $stats = [
            'total_events' => count(self::$eventHistory),
            'total_handlers' => 0,
            'events_with_handlers' => 0,
        ];
        
        foreach (self::$handlers as $event => $handlers) {
            $stats['total_handlers'] += count($handlers);
            $stats['events_with_handlers']++;
        }
        
        return $stats;
    }
}