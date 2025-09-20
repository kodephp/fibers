<?php

namespace Nova\Fibers\Core;

/**
 * EventLoop - 事件循环类
 * 
 * 管理异步操作和事件调度
 */
class EventLoop
{
    /**
     * @var array 定时器列表
     */
    private static array $timers = [];
    
    /**
     * @var array 等待的纤程列表
     */
    private static array $waitingFibers = [];
    
    /**
     * @var bool 事件循环是否正在运行
     */
    private static bool $running = false;
    
    /**
     * 运行事件循环
     *
     * @return void
     */
    public static function run(): void
    {
        self::$running = true;
        
        while (self::$running && (self::hasPendingTimers() || self::hasWaitingFibers())) {
            // 处理定时器
            self::processTimers();
            
            // 处理等待的纤程
            self::processWaitingFibers();
            
            // 避免过度占用CPU
            usleep(1000); // 1ms
        }
    }
    
    /**
     * 停止事件循环
     *
     * @return void
     */
    public static function stop(): void
    {
        self::$running = false;
    }
    
    /**
     * 添加延迟任务
     *
     * @param float $delay 延迟时间（秒）
     * @param callable $callback 回调函数
     * @return string 定时器ID
     */
    public static function delay(float $delay, callable $callback): string
    {
        $timerId = uniqid('timer_', true);
        $executionTime = microtime(true) + $delay;
        
        self::$timers[$timerId] = [
            'executionTime' => $executionTime,
            'callback' => $callback
        ];
        
        return $timerId;
    }
    
    /**
     * 取消定时器
     *
     * @param string $timerId 定时器ID
     * @return void
     */
    public static function cancel(string $timerId): void
    {
        unset(self::$timers[$timerId]);
    }
    
    /**
     * 检查是否有待处理的定时器
     *
     * @return bool
     */
    private static function hasPendingTimers(): bool
    {
        return !empty(self::$timers);
    }
    
    /**
     * 检查是否有等待的纤程
     *
     * @return bool
     */
    private static function hasWaitingFibers(): bool
    {
        return !empty(self::$waitingFibers);
    }
    
    /**
     * 处理定时器
     *
     * @return void
     */
    private static function processTimers(): void
    {
        $currentTime = microtime(true);
        
        foreach (self::$timers as $timerId => $timer) {
            if ($currentTime >= $timer['executionTime']) {
                // 执行回调
                $callback = $timer['callback'];
                if (is_callable($callback)) {
                    $callback();
                }
                
                // 移除已执行的定时器
                unset(self::$timers[$timerId]);
            }
        }
    }
    
    /**
     * 处理等待的纤程
     *
     * @return void
     */
    private static function processWaitingFibers(): void
    {
        // 这里应该实现等待纤程的处理逻辑
        // 由于我们还没有实现完整的纤程调度，这里只是示例
        echo "Processing " . count(self::$waitingFibers) . " waiting fibers\n";
        
        // 清空等待列表
        self::$waitingFibers = [];
    }
}