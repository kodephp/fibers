<?php

declare(strict_types=1);

namespace Nova\Fibers\Task;

use Closure;
use Fiber;
use Nova\Fibers\Contracts\Runnable;
use Nova\Fibers\Support\Environment;
use RuntimeException;
use Throwable;

/**
 * 任务执行器
 * 
 * 提供任务运行、超时控制等功能
 */
class TaskRunner
{
    /**
     * 运行任务
     * 
     * @param callable|Runnable $task 任务
     * @param float|null $timeout 超时时间（秒）
     * @return mixed 任务结果
     * @throws RuntimeException
     */
    public static function run(callable|Runnable $task, ?float $timeout = null): mixed
    {
        // 检查环境支持
        if (!Environment::checkFiberSupport()) {
            throw new RuntimeException('Fiber requires PHP 8.1 or higher');
        }

        // 如果没有设置超时，直接执行任务
        if ($timeout === null) {
            if ($task instanceof Runnable) {
                return $task->run();
            }

            return $task();
        }

        // 使用超时控制执行任务
        return self::runWithTimeout($task, $timeout);
    }

    /**
     * 并发运行多个任务
     * 
     * @param array $tasks 任务数组
     * @param float|null $timeout 超时时间（秒）
     * @return array 任务结果
     * @throws RuntimeException
     */
    public static function concurrent(array $tasks, ?float $timeout = null): array
    {
        // 检查环境支持
        if (!Environment::checkFiberSupport()) {
            throw new RuntimeException('Fiber requires PHP 8.1 or higher');
        }

        $results = [];
        $fibers = [];
        $exceptions = [];

        // 创建纤程执行任务
        foreach ($tasks as $key => $task) {
            $fiber = new Fiber(function () use ($task) {
                try {
                    if ($task instanceof Runnable) {
                        return $task->run();
                    }

                    if (is_callable($task)) {
                        return $task();
                    }

                    throw new \InvalidArgumentException('Task must be callable or implement Runnable interface');
                } catch (Throwable $e) {
                    return $e;
                }
            });

            $fiber->start();
            $fibers[$key] = $fiber;
        }

        // 等待所有任务完成或超时
        $startTime = microtime(true);
        $hasTimeout = $timeout !== null;

        while (!empty($fibers)) {
            if ($hasTimeout && (microtime(true) - $startTime) >= $timeout) {
                // 中断所有未完成的纤程
                foreach ($fibers as $fiber) {
                    if ($fiber->isRunning()) {
                        // 注意：PHP Fiber 无法强制中断运行中的纤程
                        // 这里只是标记超时，实际中断需要任务自行检查
                    }
                }
                throw new RuntimeException("Task execution timed out after {$timeout} seconds");
            }

            foreach ($fibers as $key => $fiber) {
                if ($fiber->isTerminated()) {
                    try {
                        $result = $fiber->getReturn();
                        if ($result instanceof Throwable) {
                            $exceptions[$key] = $result;
                        } else {
                            $results[$key] = $result;
                        }
                    } catch (Throwable $e) {
                        $exceptions[$key] = $e;
                    }
                    unset($fibers[$key]);
                } elseif ($fiber->isSuspended()) {
                    // 恢复挂起的纤程
                    $fiber->resume();
                }
            }

            // 避免忙等待，短暂休眠
            if (!empty($fibers)) {
                usleep(1000); // 1ms
            }
        }

        // 如果有异常，抛出第一个异常
        if (!empty($exceptions)) {
            throw new RuntimeException('One or more tasks failed', 0, $exceptions[0]);
        }

        return $results;
    }

    /**
     * 带超时控制的任务执行
     * 
     * @param callable|Runnable $task 任务
     * @param float $timeout 超时时间（秒）
     * @return mixed 任务结果
     * @throws RuntimeException
     */
    public static function runWithTimeout(callable|Runnable $task, float $timeout): mixed
    {
        $result = null;
        $exception = null;
        $completed = false;

        $fiber = new Fiber(function () use ($task, &$result, &$exception, &$completed) {
            try {
                if ($task instanceof Runnable) {
                    $result = $task->run();
                } else {
                    $result = $task();
                }
            } catch (Throwable $e) {
                $exception = $e;
            } finally {
                $completed = true;
            }
        });

        $fiber->start();

        $startTime = microtime(true);
        while (!$completed && (microtime(true) - $startTime) < $timeout) {
            if ($fiber->isSuspended()) {
                $fiber->resume();
            }
            usleep(1000); // 1ms
        }

        if (!$completed) {
            throw new RuntimeException("Task execution timed out after {$timeout} seconds");
        }

        if ($exception !== null) {
            throw new RuntimeException('Task failed', 0, $exception);
        }

        return $result;
    }
}
