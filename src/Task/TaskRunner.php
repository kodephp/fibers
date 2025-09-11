<?php

declare(strict_types=1);

namespace Nova\Fibers\Task;

use Nova\Fibers\Contracts\Runnable;
use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Support\Environment;
use Throwable;

/**
 * 任务执行器
 *
 * @package Nova\Fibers\Task
 */
class TaskRunner
{
    /**
     * 运行任务
     *
     * @param callable|Runnable $task 任务
     * @param array $context 上下文数据
     * @return mixed 任务执行结果
     * @throws Throwable
     */
    public static function run(callable|Runnable $task, array $context = []): mixed
    {
        // 检查环境是否支持纤程
        if (!Environment::checkFiberSupport()) {
            throw new \RuntimeException('Fiber support is not available in this environment.');
        }

        // 如果是 Runnable 接口实例，调用 run 方法
        if ($task instanceof Runnable) {
            return $task->run($context);
        }

        // 如果是闭包或可调用对象，直接执行
        if (is_callable($task)) {
            return $task($context);
        }

        throw new \InvalidArgumentException('Task must be callable or implement Runnable interface.');
    }

    /**
     * 并行运行多个任务
     *
     * @param array $tasks 任务数组
     * @param array $options 纤程池选项
     * @return array 执行结果数组
     * @throws Throwable
     */
    public static function concurrent(array $tasks, array $options = []): array
    {
        // 检查环境是否支持纤程
        if (!Environment::checkFiberSupport()) {
            throw new \RuntimeException('Fiber support is not available in this environment.');
        }

        // 创建纤程池
        $pool = new FiberPool($options);

        // 提交任务并获取结果
        return $pool->concurrent($tasks);
    }

    /**
     * 带超时控制的任务执行
     *
     * @param callable|Runnable $task 任务
     * @param float $timeout 超时时间（秒）
     * @param array $context 上下文数据
     * @return mixed 任务执行结果
     * @throws Throwable
     */
    public static function runWithTimeout(callable|Runnable $task, float $timeout, array $context = []): mixed
    {
        // 检查环境是否支持纤程
        if (!Environment::checkFiberSupport()) {
            throw new \RuntimeException('Fiber support is not available in this environment.');
        }

        // 创建带超时的纤程
        $fiber = new \Fiber(function () use ($task, $context) {
            return self::run($task, $context);
        });

        // 启动纤程
        $fiber->start();

        // 设置超时
        $startTime = microtime(true);

        while (!$fiber->isTerminated()) {
            // 检查是否超时
            if (microtime(true) - $startTime > $timeout) {
                throw new \RuntimeException("Task execution timed out after {$timeout} seconds.");
            }

            // 暂停当前纤程，允许其他纤程运行
            \Fiber::suspend();
        }

        // 获取结果
        return $fiber->getReturn();
    }
}
