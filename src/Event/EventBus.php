<?php

namespace Nova\Fibers\Event;

/**
 * EventBus - 事件总线类
 * 
 * 实现发布/订阅模式，支持事件监听和触发
 */
class EventBus
{
    /**
     * @var array 事件监听器列表
     */
    private static array $listeners = [];
    
    /**
     * @var array 事件历史记录
     */
    private static array $eventHistory = [];
    
    /**
     * 添加事件监听器
     *
     * @param string $event 事件名称
     * @param callable $listener 监听器回调
     * @param int $priority 优先级（越大越优先）
     * @return void
     */
    public static function on(string $event, callable $listener, int $priority = 0): void
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }
        
        self::$listeners[$event][] = [
            'callback' => $listener,
            'priority' => $priority
        ];
        
        // 按优先级排序
        usort(self::$listeners[$event], function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
    }
    
    /**
     * 触发事件
     *
     * @param string $event 事件名称
     * @param mixed $data 事件数据
     * @return void
     */
    public static function fire(string $event, mixed $data = null): void
    {
        // 记录事件历史
        self::$eventHistory[] = [
            'event' => $event,
            'data' => $data,
            'timestamp' => microtime(true)
        ];
        
        // 限制历史记录数量
        if (count(self::$eventHistory) > 1000) {
            array_shift(self::$eventHistory);
        }
        
        // 触发监听器
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $listener) {
                $callback = $listener['callback'];
                if (is_callable($callback)) {
                    $callback($data);
                }
            }
        }
    }
    
    /**
     * 移除事件监听器
     *
     * @param string $event 事件名称
     * @param callable|null $listener 特定监听器，如果为null则移除所有监听器
     * @return void
     */
    public static function off(string $event, ?callable $listener = null): void
    {
        if ($listener === null) {
            // 移除所有监听器
            unset(self::$listeners[$event]);
        } else {
            // 移除特定监听器
            if (isset(self::$listeners[$event])) {
                foreach (self::$listeners[$event] as $index => $item) {
                    if ($item['callback'] === $listener) {
                        unset(self::$listeners[$event][$index]);
                    }
                }
                
                // 重新索引数组
                self::$listeners[$event] = array_values(self::$listeners[$event]);
            }
        }
    }
    
    /**
     * 获取事件历史记录
     *
     * @return array
     */
    public static function getEventHistory(): array
    {
        return self::$eventHistory;
    }
    
    /**
     * 清空事件历史记录
     *
     * @return void
     */
    public static function clearEventHistory(): void
    {
        self::$eventHistory = [];
    }
}
