<?php

declare(strict_types=1);

namespace Nova\Fibers\Event;

use Nova\Fibers\Channel\Channel;

/**
 * 事件总线实现
 * 
 * 提供发布/订阅模式的事件处理机制，支持在纤程间传递事件
 */
class EventBus
{
    /**
     * 事件监听器映射
     * 
     * @var array
     */
    protected static array $listeners = [];

    /**
     * 事件通道
     * 
     * @var Channel|null
     */
    protected static ?Channel $channel = null;

    /**
     * 初始化事件总线
     * 
     * @return void
     */
    protected static function initialize(): void
    {
        if (self::$channel === null) {
            self::$channel = new Channel('event-bus', 100);
            
            // 启动事件处理纤程
            $fiber = new \Fiber(function () {
                while (!self::$channel->isClosed()) {
                    $event = self::$channel->pop();
                    if ($event !== false && $event !== null) {
                        self::dispatchEvent($event);
                    }
                }
            });
            $fiber->start();
        }
    }

    /**
     * 注册事件监听器
     * 
     * @param string $event 事件名称
     * @param callable $listener 监听器回调函数
     * @return void
     */
    public static function on(string $event, callable $listener): void
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }
        
        self::$listeners[$event][] = $listener;
    }

    /**
     * 触发事件
     * 
     * @param object $event 事件对象
     * @return void
     */
    public static function fire(object $event): void
    {
        self::initialize();
        self::$channel->push($event);
    }

    /**
     * 分发事件给所有监听器
     * 
     * @param object $event 事件对象
     * @return void
     */
    protected static function dispatchEvent(object $event): void
    {
        $eventName = get_class($event);
        
        // 触发具体事件类型的监听器
        if (isset(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $listener) {
                // 在新的纤程中执行监听器
                $fiber = new \Fiber(function () use ($listener, $event) {
                    $listener($event);
                });
                $fiber->start();
            }
        }
        
        // 触发通配符监听器
        foreach (self::$listeners as $pattern => $listeners) {
            if (str_contains($pattern, '*') && self::matchesPattern($eventName, $pattern)) {
                foreach ($listeners as $listener) {
                    // 在新的纤程中执行监听器
                    $fiber = new \Fiber(function () use ($listener, $event) {
                        $listener($event);
                    });
                    $fiber->start();
                }
            }
        }
    }

    /**
     * 检查事件名称是否匹配模式
     * 
     * @param string $eventName 事件名称
     * @param string $pattern 模式
     * @return bool 是否匹配
     */
    protected static function matchesPattern(string $eventName, string $pattern): bool
    {
        // 将模式转换为正则表达式
        $regex = str_replace('*', '.*', preg_quote($pattern, '/'));
        return (bool) preg_match("/^{$regex}$/", $eventName);
    }

    /**
     * 移除事件监听器
     * 
     * @param string $event 事件名称
     * @param callable|null $listener 监听器回调函数，如果为null则移除所有监听器
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
                $index = array_search($listener, self::$listeners[$event], true);
                if ($index !== false) {
                    unset(self::$listeners[$event][$index]);
                }
            }
        }
    }

    /**
     * 获取事件监听器数量
     * 
     * @param string $event 事件名称
     * @return int 监听器数量
     */
    public static function listenerCount(string $event): int
    {
        return isset(self::$listeners[$event]) ? count(self::$listeners[$event]) : 0;
    }

    /**
     * 关闭事件总线
     * 
     * @return void
     */
    public static function close(): void
    {
        if (self::$channel !== null) {
            self::$channel->close();
            self::$channel = null;
        }
        
        self::$listeners = [];
    }
    
    /**
     * 重置事件总线（用于测试）
     * 
     * @return void
     */
    public static function reset(): void
    {
        self::$listeners = [];
        if (self::$channel !== null) {
            self::$channel->close();
            self::$channel = null;
        }
    }
}
