<?php

namespace Nova\Fibers\Facades;

use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Channel\Channel;
use Nova\Fibers\Event\EventBus;

/**
 * FiberManager - 纤程管理器门面类
 * 
 * 提供便捷的静态方法访问纤程功能
 */
class FiberManager
{
    /**
     * @var FiberPool|null 默认纤程池实例
     */
    private static ?FiberPool $defaultPool = null;
    
    /**
     * @var array 通道实例列表
     */
    private static array $channels = [];
    
    /**
     * 运行一个纤程任务
     *
     * @param callable $task 任务回调
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 任务执行结果
     */
    public static function run(callable $task, ?float $timeout = null): mixed
    {
        // 创建一个临时纤程并执行任务
        $fiber = new \Fiber(function() use ($task) {
            return $task();
        });
        
        $fiber->start();
        
        // 如果设置了超时，需要特殊处理
        if ($timeout !== null) {
            // 这里应该实现超时控制逻辑
            // 由于我们还没有实现完整的事件循环，这里只是示例
            echo "Timeout control is not fully implemented yet\n";
        }
        
        // 等待纤程完成
        while ($fiber->isStarted() && !$fiber->isTerminated()) {
            // 在实际实现中，这里会运行事件循环
            // 暂时使用简单的循环等待
            usleep(1000); // 1ms
        }
        
        return $fiber->getReturn();
    }
    
    /**
     * 获取默认纤程池实例
     *
     * @param array $options 纤程池配置选项
     * @return FiberPool
     */
    public static function pool(array $options = []): FiberPool
    {
        if (self::$defaultPool === null) {
            self::$defaultPool = new FiberPool($options);
        }
        
        return self::$defaultPool;
    }
    
    /**
     * 创建或获取通道实例
     *
     * @param string $name 通道名称
     * @param int $bufferSize 缓冲区大小
     * @return Channel
     */
    public static function channel(string $name, int $bufferSize = 0): Channel
    {
        if (!isset(self::$channels[$name])) {
            self::$channels[$name] = new Channel($name, $bufferSize);
        }
        
        return self::$channels[$name];
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
        EventBus::fire($event, $data);
    }
    
    /**
     * 监听事件
     *
     * @param string $event 事件名称
     * @param callable $listener 监听器回调
     * @param int $priority 优先级
     * @return void
     */
    public static function on(string $event, callable $listener, int $priority = 0): void
    {
        EventBus::on($event, $listener, $priority);
    }
}