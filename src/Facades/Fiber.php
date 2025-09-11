<?php

declare(strict_types=1);

namespace Nova\Fibers\Facades;

use Fiber as PhpFiber;
use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Support\Environment;
use Closure;

/**
 * Fiber门面类
 * 
 * 提供简化的纤程操作接口，支持一键运行任务和纤程池管理
 */
class Fiber
{
    /**
     * 默认纤程池实例
     * 
     * @var FiberPool|null
     */
    protected static ?FiberPool $defaultPool = null;

    /**
     * 一键运行任务
     * 
     * @param callable $task 要执行的任务
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 任务执行结果
     */
    public static function run(callable $task, ?float $timeout = null): mixed
    {
        // 检查环境是否支持纤程
        if (!Environment::supportsFibers()) {
            throw new \RuntimeException('Fibers are not supported in this PHP version (' . PHP_VERSION . ')');
        }

        // 创建纤程并执行任务
        $fiber = new PhpFiber($task);
        $fiber->start();

        // 等待任务完成或超时
        $startTime = microtime(true);
        
        while (!$fiber->isTerminated()) {
            if ($fiber->isSuspended()) {
                $fiber->resume();
            }
            
            // 检查是否超时
            if ($timeout !== null && (microtime(true) - $startTime) >= $timeout) {
                throw new \RuntimeException("Task execution timed out after {$timeout} seconds");
            }
            
            // 避免CPU占用过高
            usleep(1000);
        }

        return $fiber->getReturn();
    }

    /**
     * 获取默认纤程池实例
     * 
     * @param array $config 纤程池配置
     * @return FiberPool 纤程池实例
     */
    public static function pool(array $config = []): FiberPool
    {
        if (self::$defaultPool === null) {
            self::$defaultPool = new FiberPool($config);
        }
        
        return self::$defaultPool;
    }

    /**
     * 并发执行多个任务
     * 
     * @param callable[] $tasks 任务数组
     * @param float|null $timeout 超时时间（秒）
     * @return array 任务执行结果
     */
    public static function concurrent(array $tasks, ?float $timeout = null): array
    {
        return self::pool()->concurrent($tasks, $timeout);
    }

    /**
     * 获取当前纤程ID
     * 
     * @return string|null 纤程ID
     */
    public static function id(): ?string
    {
        $fiber = PhpFiber::getCurrent();
        return $fiber ? spl_object_hash($fiber) : null;
    }

    /**
     * 挂起当前纤程
     * 
     * @param mixed $value 传递给恢复纤程的值
     * @return mixed 恢复纤程时传递的值
     */
    public static function suspend(mixed $value = null): mixed
    {
        return PhpFiber::suspend($value);
    }

    /**
     * 恢复纤程执行
     * 
     * @param PhpFiber $fiber 要恢复的纤程
     * @param mixed $value 传递给纤程的值
     * @return mixed 纤程返回值
     */
    public static function resume(PhpFiber $fiber, mixed $value = null): mixed
    {
        if ($fiber->isSuspended()) {
            return $fiber->resume($value);
        }
        
        throw new \RuntimeException('Cannot resume a fiber that is not suspended');
    }

    /**
     * 检查当前是否在纤程中运行
     * 
     * @return bool 是否在纤程中
     */
    public static function isInFiber(): bool
    {
        return PhpFiber::getCurrent() !== null;
    }
}
