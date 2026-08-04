<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Closure;
use Kode\Fibers\Contracts\Runnable;
use Throwable;

/**
 * 运行时桥接层
 *
 * 把「同步调用风格」的门面 API（Fibers::run() / concurrent() / sleep() 等）
 * 接到 {@see Scheduler} 事件循环上：
 *
 * - 已在协程内 → 直接复用当前调度器，不再新建事件循环（可安全嵌套）
 * - 不在协程内 → 临时创建调度器，驱动到任务结束后返回
 *
 * 这样调用方无需感知事件循环，同时超时能够真正中断任务，
 * 而不是「跑完再比对耗时」的事后断言。
 */
final class Runtime
{
    /**
     * 禁止实例化
     */
    private function __construct()
    {
    }

    /**
     * 当前可用的调度器（协程内为所属调度器，否则为进程级默认调度器）
     */
    public static function scheduler(): Scheduler
    {
        return Scheduler::current() ?? Scheduler::default();
    }

    /**
     * 是否处于运行中的事件循环内部
     */
    public static function inLoop(): bool
    {
        return Scheduler::inCoroutine();
    }

    /**
     * 执行单个任务并返回结果
     *
     * @param callable|Runnable $task    任务
     * @param float|null        $timeout 超时秒数，超时后任务会被真正取消
     * @throws TimeoutException 超时
     * @throws Throwable        任务自身抛出的异常
     */
    public static function execute(callable|Runnable $task, ?float $timeout = null): mixed
    {
        $callable = self::toClosure($task);

        if (Scheduler::inCoroutine()) {
            $scheduler = Scheduler::current();

            return $timeout === null
                ? $callable()
                : $scheduler->withTimeout($callable, $timeout);
        }

        $scheduler = new Scheduler();

        $coroutine = $scheduler->go(static function () use ($scheduler, $callable, $timeout): mixed {
            return $timeout === null
                ? $callable()
                : $scheduler->withTimeout($callable, $timeout);
        });

        return $coroutine->join();
    }

    /**
     * 并发执行一组任务（settle 语义：失败以 Throwable 形式出现在结果数组中）
     *
     * @param  iterable<array-key, callable|Runnable> $tasks
     * @param  float|null $timeout     单个任务的超时秒数
     * @param  int|null   $concurrency 并发上限，null 表示不限制
     * @return array<array-key, mixed>
     */
    public static function settleAll(iterable $tasks, ?float $timeout = null, ?int $concurrency = null): array
    {
        return self::dispatch($tasks, $timeout, $concurrency, true);
    }

    /**
     * 并发执行一组任务，任一任务失败即向外抛出
     *
     * @param  iterable<array-key, callable|Runnable> $tasks
     * @return array<array-key, mixed>
     * @throws Throwable
     */
    public static function all(iterable $tasks, ?float $timeout = null, ?int $concurrency = null): array
    {
        return self::dispatch($tasks, $timeout, $concurrency, false);
    }

    /**
     * 协程友好的休眠：在事件循环内让出执行权，否则退化为 usleep
     */
    public static function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        if (Scheduler::inCoroutine()) {
            Scheduler::current()->sleep($seconds);

            return;
        }

        usleep((int) round($seconds * 1_000_000));
    }

    /**
     * 让出一次执行权（不在协程内时为空操作）
     */
    public static function yieldNow(): void
    {
        if (Scheduler::inCoroutine()) {
            Scheduler::current()->yieldNow();
        }
    }

    /**
     * 统一把任务转换为 Closure
     */
    public static function toClosure(callable|Runnable $task): Closure
    {
        if ($task instanceof Runnable) {
            return static fn(): mixed => $task->run();
        }

        return $task instanceof Closure ? $task : Closure::fromCallable($task);
    }

    /**
     * 并发调度实现
     *
     * @param  iterable<array-key, callable|Runnable> $tasks
     * @return array<array-key, mixed>
     */
    private static function dispatch(iterable $tasks, ?float $timeout, ?int $concurrency, bool $settle): array
    {
        $nested = Scheduler::inCoroutine();
        $scheduler = $nested ? Scheduler::current() : new Scheduler();

        $limit = ($concurrency !== null && $concurrency > 0) ? new Semaphore($concurrency, $scheduler) : null;

        $coroutines = [];

        foreach ($tasks as $key => $task) {
            $callable = self::toClosure($task);

            $body = static function () use ($scheduler, $callable, $timeout): mixed {
                return $timeout === null
                    ? $callable()
                    : $scheduler->withTimeout($callable, $timeout);
            };

            $coroutines[$key] = $scheduler->go(
                static function () use ($body, $limit, $settle): mixed {
                    try {
                        return $limit === null ? $body() : $limit->run($body);
                    } catch (Throwable $e) {
                        if ($settle) {
                            return $e;
                        }

                        throw $e;
                    }
                },
                is_string($key) ? $key : null
            );
        }

        if ($coroutines === []) {
            return [];
        }

        if (!$nested) {
            $scheduler->run(static function () use ($coroutines): bool {
                foreach ($coroutines as $coroutine) {
                    if (!$coroutine->isFinished()) {
                        return false;
                    }
                }

                return true;
            });
        }

        $results = [];

        foreach ($coroutines as $key => $coroutine) {
            if ($nested) {
                $results[$key] = $settle ? self::safeJoin($coroutine) : $coroutine->join();

                continue;
            }

            if ($settle) {
                try {
                    $results[$key] = $coroutine->result();
                } catch (Throwable $e) {
                    $results[$key] = $e;
                }

                continue;
            }

            $results[$key] = $coroutine->result();
        }

        return $results;
    }

    /**
     * 等待协程结束，异常作为返回值
     */
    private static function safeJoin(Coroutine $coroutine): mixed
    {
        try {
            return $coroutine->join();
        } catch (Throwable $e) {
            return $e;
        }
    }
}
