<?php

declare(strict_types=1);

namespace Kode\Fibers\Task;

use Kode\Context\Context;
use Kode\Fibers\Concurrency\CancelledException;
use Kode\Fibers\Concurrency\Runtime;
use Kode\Fibers\Concurrency\TimeoutException;
use Kode\Fibers\Contracts\Runnable;
use Kode\Fibers\Exceptions\FiberException;

/**
 * Task runner for fiber tasks
 *
 * 基于 {@see \Kode\Fibers\Concurrency\Scheduler} 的任务执行入口，提供超时、
 * 重试、并发、优先级与可取消任务能力。
 *
 * 关键行为（v4 起）：
 * - 超时是「真中断」：到期后任务会在下一个挂起点被取消，而不是跑完再比对耗时；
 * - 重试与休眠在协程内让出执行权，不再阻塞整个事件循环；
 * - 取消 / 超时异常保留原始类型，不会被包装成普通 FiberException。
 */
class TaskRunner
{
    /**
     * Run a task
     *
     * @param callable|Runnable $task
     * @param float|null $timeout 超时秒数
     * @param array $context 可选上下文数据
     * @return mixed
     * @throws FiberException If task execution fails
     */
    public static function run(callable|Runnable $task, ?float $timeout = null, array $context = []): mixed
    {
        $body = static function () use ($task, $context): mixed {
            if ($context !== []) {
                Context::merge($context);
            }

            return $task instanceof Runnable ? $task->run() : $task();
        };

        try {
            return Runtime::execute($body, $timeout);
        } catch (CancelledException | FiberException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new FiberException('Task execution failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Run a task with timeout
     *
     * @param callable|Runnable $task
     * @param float $timeout
     * @param array $context Optional context data
     * @return mixed
     * @throws TimeoutException 超时
     * @throws FiberException   任务失败
     */
    public static function runWithTimeout(callable|Runnable $task, float $timeout, array $context = []): mixed
    {
        if ($timeout <= 0) {
            throw new FiberException('超时时间必须大于 0 秒');
        }

        return static::run($task, $timeout, $context);
    }

    /**
     * Run a task with retry mechanism
     *
     * @param callable|Runnable $task
     * @param int $maxRetries 最大重试次数
     * @param float $retryDelay 重试间隔（秒）
     * @param ?float $timeout 单次尝试的超时秒数
     * @return mixed
     * @throws FiberException If task fails after all retries
     */
    public static function retry(
        callable|Runnable $task,
        int $maxRetries = 3,
        float $retryDelay = 1,
        ?float $timeout = null
    ): mixed {
        $maxRetries = max(0, $maxRetries);
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $maxRetries) {
            if ($attempt > 0 && $retryDelay > 0) {
                Runtime::sleep($retryDelay);
            }

            try {
                return static::run($task, $timeout);
            } catch (CancelledException | TimeoutException $e) {
                // 取消 / 超时不应被当作可重试失败，原样向上抛出
                throw $e;
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;
            }
        }

        throw new FiberException(
            sprintf('Task failed after %d retries: %s', $maxRetries, $lastException?->getMessage() ?? 'unknown'),
            (int)($lastException?->getCode() ?? 0),
            $lastException
        );
    }

    /**
     * Run multiple tasks concurrently
     *
     * 返回值与输入键一一对应；失败的任务以 Throwable 形式出现在结果中。
     *
     * @param array $tasks
     * @param array $options 支持 timeout、concurrency
     * @return array
     * @throws FiberException If concurrent execution fails
     */
    public static function concurrent(array $tasks, array $options = []): array
    {
        if ($tasks === []) {
            return [];
        }

        $timeout = isset($options['timeout']) ? (float)$options['timeout'] : null;
        $concurrency = isset($options['concurrency']) ? (int)$options['concurrency'] : null;

        try {
            return Runtime::settleAll($tasks, $timeout, $concurrency);
        } catch (\Throwable $e) {
            throw new FiberException('Concurrent task execution failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Run tasks with priorities
     *
     * 相同优先级的任务并发执行，不同优先级按从小到大依次执行。
     *
     * @param array $priorityTasks Array of [priority => task, ...]
     * @param array $options
     * @return array
     * @throws FiberException If priority execution fails
     */
    public static function prioritized(array $priorityTasks, array $options = []): array
    {
        try {
            ksort($priorityTasks);

            $timeout = isset($options['timeout']) ? (float)$options['timeout'] : null;
            $results = [];

            foreach ($priorityTasks as $priority => $task) {
                $results[$priority] = static::run($task, $timeout);
            }

            return $results;
        } catch (\Throwable $e) {
            throw new FiberException('Prioritized task execution failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a cancellable task
     *
     * @param callable|Runnable $task
     * @param callable|null $cancelCallback 取消时执行的回调
     * @return array{runTask: \Closure, cancelTask: \Closure, isCancelled: \Closure}
     */
    public static function cancellable(
        callable|Runnable $task,
        ?callable $cancelCallback = null
    ): array {
        $cancelled = false;

        $wrappedTask = static function () use ($task, &$cancelled): mixed {
            if ($cancelled) {
                throw new CancelledException('Task was cancelled before execution');
            }

            return $task instanceof Runnable ? $task->run() : $task();
        };

        $cancel = static function () use (&$cancelled, $cancelCallback): void {
            if ($cancelled) {
                return;
            }

            $cancelled = true;

            if ($cancelCallback !== null) {
                try {
                    $cancelCallback();
                } catch (\Throwable) {
                    // 取消回调中的异常不影响取消动作本身
                }
            }
        };

        return [
            'runTask' => static fn(?float $timeout = null): mixed => static::run($wrappedTask, $timeout),
            'cancelTask' => $cancel,
            'isCancelled' => static fn(): bool => $cancelled,
        ];
    }
}
