<?php

declare(strict_types=1);

namespace Nova\Fibers\Event;

/**
 * 事件总线类
 *
 * @package Nova\Fibers\Event
 */
class EventBus
{
    /**
     * @var array 事件监听器
     */
    protected static array $listeners = [];

    /**
     * @var array 事件队列
     */
    protected static array $eventQueue = [];

    /**
     * 注册事件监听器
     *
     * @param string $event 事件名称
     * @param callable $listener 监听器
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
        $eventName = get_class($event);

        // 触发类名匹配的监听器
        if (isset(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $listener) {
                $listener($event);
            }
        }

        // 触发接口匹配的监听器
        $interfaces = class_implements($event);
        foreach ($interfaces as $interface) {
            if (isset(self::$listeners[$interface])) {
                foreach (self::$listeners[$interface] as $listener) {
                    $listener($event);
                }
            }
        }
    }

    /**
     * 移除事件监听器
     *
     * @param string $event 事件名称
     * @param callable|null $listener 特定监听器，如果为 null 则移除所有监听器
     * @return void
     */
    public static function off(string $event, ?callable $listener = null): void
    {
        if ($listener === null) {
            unset(self::$listeners[$event]);
            return;
        }

        if (isset(self::$listeners[$event])) {
            $index = array_search($listener, self::$listeners[$event], true);
            if ($index !== false) {
                unset(self::$listeners[$event][$index]);
                self::$listeners[$event] = array_values(self::$listeners[$event]);
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
     * 重置事件总线（用于测试）
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$listeners = [];
        self::$eventQueue = [];
    }
}
