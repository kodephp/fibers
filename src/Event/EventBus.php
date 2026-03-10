<?php
declare(strict_types=1);

namespace Kode\Fibers\Event;

use Kode\Fibers\Context\Context;
use Closure;
use SplPriorityQueue;
use RuntimeException;

/**
 * 事件总线 - 管理事件监听和触发
 */
class EventBus
{
    /**
     * 事件监听器映射
     *
     * @var array<string, array<int, array{callback: Closure, priority: int, once: bool}>>
     */
    protected static array $listeners = [];

    /**
     * 注册的事件中间件
     *
     * @var array<string, array<int, Closure>>
     */
    protected static array $middlewares = [];

    /**
     * 全局中间件
     *
     * @var array<int, Closure>
     */
    protected static array $globalMiddlewares = [];

    /**
     * 注册事件监听器
     *
     * @param string $event 事件名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级，值越大优先级越高
     * @param bool $once 是否只执行一次
     * @return self
     */
    public static function on(string $event, callable $callback, int $priority = 0, bool $once = false): self
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        // 转换为Closure以支持绑定
        if (!($callback instanceof Closure)) {
            $callback = Closure::fromCallable($callback);
        }

        // 添加监听器
        self::$listeners[$event][] = [
            'callback' => $callback,
            'priority' => $priority,
            'once' => $once
        ];

        // 按优先级排序
        usort(self::$listeners[$event], static function (array $a, array $b): int {
            return $b['priority'] <=> $a['priority'];
        });

        return new self();
    }

    /**
     * 注册一次性事件监听器
     *
     * @param string $event 事件名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     * @return self
     */
    public static function once(string $event, callable $callback, int $priority = 0): self
    {
        return self::on($event, $callback, $priority, true);
    }

    /**
     * 触发事件
     *
     * @param string|Event $event 事件对象或事件名称
     * @param mixed $data 事件数据
     * @return array 所有监听器的返回值
     */
    public static function fire(string|Event $event, mixed $data = null): array
    {
        // 如果传入的是事件对象，获取事件名称
        if ($event instanceof Event) {
            $eventName = $event->getName();
            $data = $event->getData();
        } else {
            $eventName = $event;
            // 如果数据是数组，转换为BaseEvent对象
            if (is_array($data) || $data === null) {
                $event = new BaseEvent($eventName, $data);
            }
        }

        // 如果没有监听器，直接返回空数组
        if (!isset(self::$listeners[$eventName])) {
            return [];
        }

        // 获取全局上下文数据
        $context = Context::getAll();

        // 执行全局中间件
        $middlewareStack = array_reverse(self::$globalMiddlewares);
        if (isset(self::$middlewares[$eventName])) {
            $middlewareStack = array_merge($middlewareStack, array_reverse(self::$middlewares[$eventName]));
        }

        // 构建执行堆栈
        $handler = static function ($event, $data, $context) use ($eventName) {
            $results = [];
            $toRemove = [];

            // 执行所有监听器
            foreach (self::$listeners[$eventName] as $index => $listener) {
                try {
                    // 在闭包中执行回调，注入上下文
                    $result = $listener['callback']($event, $data, $context);
                    $results[] = $result;

                    // 如果是一次性监听器，标记为移除
                    if ($listener['once']) {
                        $toRemove[] = $index;
                    }
                } catch (\Throwable $e) {
                    // 错误处理可以在这里添加日志或重新抛出异常
                    error_log('Error in event listener: ' . $e->getMessage());
                    // 可以选择是否继续执行其他监听器
                }
            }

            // 移除一次性监听器
            foreach (array_reverse($toRemove) as $index) {
                unset(self::$listeners[$eventName][$index]);
            }

            // 重建索引
            if (!empty($toRemove)) {
                self::$listeners[$eventName] = array_values(self::$listeners[$eventName]);
            }

            return $results;
        };

        // 应用中间件
        foreach ($middlewareStack as $middleware) {
            $nextHandler = $handler;
            $handler = static function ($event, $data, $context) use ($middleware, $nextHandler) {
                return $middleware($event, $data, $context, $nextHandler);
            };
        }

        // 执行处理程序
        return $handler($event, $data, $context);
    }

    /**
     * 移除事件监听器
     *
     * @param string $event 事件名称
     * @param callable|null $callback 回调函数，如果为null则移除所有
     * @return self
     */
    public static function off(string $event, callable $callback = null): self
    {
        if (!isset(self::$listeners[$event])) {
            return new self();
        }

        if ($callback === null) {
            // 移除所有监听器
            unset(self::$listeners[$event]);
            return new self();
        }

        // 转换为Closure以支持比较
        if (!($callback instanceof Closure)) {
            $callback = Closure::fromCallable($callback);
        }

        // 查找并移除指定的监听器
        foreach (self::$listeners[$event] as $index => $listener) {
            // 注意：这里的比较可能不准确，因为Closure对象的比较是基于引用的
            // 实际应用中可能需要更复杂的比较方法
            if ($listener['callback'] === $callback) {
                unset(self::$listeners[$event][$index]);
                // 重建索引
                self::$listeners[$event] = array_values(self::$listeners[$event]);
                break;
            }
        }

        return new self();
    }

    /**
     * 移除所有事件监听器
     *
     * @return self
     */
    public static function removeAll(): self
    {
        self::$listeners = [];
        self::$middlewares = [];
        return new self();
    }

    /**
     * 获取特定事件的监听器数量
     *
     * @param string $event 事件名称
     * @return int
     */
    public static function getListenerCount(string $event): int
    {
        return isset(self::$listeners[$event]) ? count(self::$listeners[$event]) : 0;
    }

    /**
     * 获取所有注册的事件名称
     *
     * @return array
     */
    public static function getRegisteredEvents(): array
    {
        return array_keys(self::$listeners);
    }

    /**
     * 注册事件中间件
     *
     * @param string $event 事件名称
     * @param callable $middleware 中间件函数
     * @return self
     */
    public static function middleware(string $event, callable $middleware): self
    {
        if (!isset(self::$middlewares[$event])) {
            self::$middlewares[$event] = [];
        }

        self::$middlewares[$event][] = Closure::fromCallable($middleware);
        return new self();
    }

    /**
     * 注册全局事件中间件
     *
     * @param callable $middleware 中间件函数
     * @return self
     */
    public static function globalMiddleware(callable $middleware): self
    {
        self::$globalMiddlewares[] = Closure::fromCallable($middleware);
        return new self();
    }

    /**
     * 使用优先级队列触发事件
     *
     * @param string|Event $event 事件对象或事件名称
     * @param mixed $data 事件数据
     * @return array
     */
    public static function fireWithPriorityQueue(string|Event $event, mixed $data = null): array
    {
        // 如果传入的是事件对象，获取事件名称
        if ($event instanceof Event) {
            $eventName = $event->getName();
            $data = $event->getData();
        } else {
            $eventName = $event;
            // 如果数据是数组，转换为BaseEvent对象
            if (is_array($data) || $data === null) {
                $event = new BaseEvent($eventName, $data);
            }
        }

        // 如果没有监听器，直接返回空数组
        if (!isset(self::$listeners[$eventName])) {
            return [];
        }

        // 创建优先级队列
        $queue = new SplPriorityQueue();
        $results = [];
        $toRemove = [];

        // 将监听器添加到优先级队列
        foreach (self::$listeners[$eventName] as $index => $listener) {
            $queue->insert([
                'callback' => $listener['callback'],
                'once' => $listener['once'],
                'index' => $index
            ], $listener['priority']);
        }

        // 执行队列中的监听器
        while (!$queue->isEmpty()) {
            $listener = $queue->extract();
            try {
                $result = $listener['callback']($event, $data);
                $results[] = $result;

                // 如果是一次性监听器，标记为移除
                if ($listener['once']) {
                    $toRemove[] = $listener['index'];
                }
            } catch (\Throwable $e) {
                // 错误处理可以在这里添加日志或重新抛出异常
                error_log('Error in event listener: ' . $e->getMessage());
            }
        }

        // 移除一次性监听器
        foreach (array_reverse($toRemove) as $index) {
            unset(self::$listeners[$eventName][$index]);
        }

        // 重建索引
        if (!empty($toRemove)) {
            self::$listeners[$eventName] = array_values(self::$listeners[$eventName]);
        }

        return $results;
    }

    /**
     * 监听多个事件
     *
     * @param array $events 事件名称数组
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     * @param bool $once 是否只执行一次
     * @return self
     */
    public static function onMany(array $events, callable $callback, int $priority = 0, bool $once = false): self
    {
        foreach ($events as $event) {
            self::on($event, $callback, $priority, $once);
        }
        return new self();
    }

    /**
     * 触发多个事件
     *
     * @param array $events 事件数组
     * @param mixed $data 事件数据
     * @return array
     */
    public static function fireMany(array $events, mixed $data = null): array
    {
        $results = [];
        foreach ($events as $event) {
            $results[$event instanceof Event ? $event->getName() : $event] = self::fire($event, $data);
        }
        return $results;
    }

    /**
     * 判断事件是否有监听器
     *
     * @param string $event 事件名称
     * @return bool
     */
    public static function hasListeners(string $event): bool
    {
        return isset(self::$listeners[$event]) && !empty(self::$listeners[$event]);
    }
}