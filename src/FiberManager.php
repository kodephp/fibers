<?php

namespace Nova\Fibers;

use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Core\EventLoop;
use Nova\Fibers\Channel\Channel;
use Nova\Fibers\Event\EventBus;
use Nova\Fibers\Support\CpuInfo;
use Nova\Fibers\Support\Environment;

/**
 * FiberManager - 主纤程管理器
 * 
 * 提供统一的纤程操作接口，包括一键启动、池化管理、通信机制等
 */
class FiberManager
{
    /**
     * @var FiberPool|null 默认纤程池实例
     */
    private static ?FiberPool $defaultPool = null;

    /**
     * 一键启动纤程任务
     *
     * @param callable $task 任务回调函数
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 任务执行结果
     */
    public static function run(callable $task, ?float $timeout = null)
    {
        // 检查环境是否支持纤程
        if (!self::isSupported()) {
            throw new \RuntimeException('Fibers are not supported in this environment');
        }

        // 如果有超时设置，使用超时控制
        if ($timeout !== null) {
            return self::runWithTimeout($task, $timeout);
        }

        // 直接运行任务
        $fiber = new \Fiber($task);
        return $fiber->start();
    }

    /**
     * 带超时控制的任务运行
     *
     * @param callable $task 任务回调函数
     * @param float $timeout 超时时间（秒）
     * @return mixed 任务执行结果
     */
    private static function runWithTimeout(callable $task, float $timeout)
    {
        $result = null;
        $exception = null;
        
        $fiber = new \Fiber(function() use ($task, &$result, &$exception) {
            try {
                $result = $task();
            } catch (\Throwable $e) {
                $exception = $e;
            }
        });
        
        $fiber->start();
        
        // 创建超时定时器
        $timerId = EventLoop::delay($timeout, function() use ($fiber) {
            if ($fiber->isStarted() && !$fiber->isTerminated()) {
                // 注意：在PHP中无法直接中断纤程执行，只能通过上下文或任务内部检查来实现
                // 这里仅作示例，实际应用中需要任务内部配合检查超时
                throw new \RuntimeException('Task timeout');
            }
        });
        
        // 运行事件循环直到纤程完成
        while ($fiber->isStarted() && !$fiber->isTerminated()) {
            EventLoop::run();
        }
        
        // 取消超时定时器
        EventLoop::cancel($timerId);
        
        if ($exception) {
            throw $exception;
        }
        
        return $result;
    }

    /**
     * 获取默认纤程池实例
     *
     * @param array $config 池配置
     * @return FiberPool 纤程池实例
     */
    public static function pool(array $config = []): FiberPool
    {
        if (self::$defaultPool === null) {
            // 合并默认配置
            $defaultConfig = [
                'size' => CpuInfo::get() * 4,
                'max_exec_time' => 30,
                'gc_interval' => 100
            ];
            
            $config = array_merge($defaultConfig, $config);
            self::$defaultPool = new FiberPool($config);
        }
        
        return self::$defaultPool;
    }

    /**
     * 创建通信通道
     *
     * @param string $name 通道名称
     * @param int $buffer 缓冲区大小
     * @return Channel 通信通道实例
     */
    public static function channel(string $name, int $buffer = 0): Channel
    {
        return Channel::make($name, $buffer);
    }

    /**
     * 检查环境是否支持纤程
     *
     * @return bool 是否支持
     */
    public static function isSupported(): bool
    {
        return version_compare(PHP_VERSION, '8.1.0', '>=');
    }

    /**
     * 启用安全析构模式（针对PHP < 8.4）
     *
     * @return void
     */
    public static function enableSafeDestructMode(): void
    {
        if (version_compare(PHP_VERSION, '8.4.0', '<')) {
            // 在PHP 8.4之前，不允许在析构函数中切换纤程
            // 这里可以设置标志位，在析构函数中检查以避免suspend调用
            // 具体实现需要在相关类的析构函数中配合检查
        }
    }

    /**
     * 运行事件循环
     *
     * @return void
     */
    public static function runEventLoop(): void
    {
        EventLoop::run();
    }

    /**
     * 停止事件循环
     *
     * @return void
     */
    public static function stopEventLoop(): void
    {
        EventLoop::stop();
    }

    /**
     * 检查运行环境
     *
     * @return array 环境诊断结果
     */
    public static function diagnoseEnvironment(): array
    {
        return Environment::diagnose();
    }
}