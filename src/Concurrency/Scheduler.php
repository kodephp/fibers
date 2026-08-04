<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Closure;
use Fiber;
use Kode\Fibers\Exceptions\FiberException;
use SplQueue;
use Throwable;
use WeakMap;

/**
 * 协作式协程调度器
 *
 * 由「就绪队列 + 定时器最小堆」构成的单线程事件循环，是本库所有非阻塞能力的地基：
 *
 * - {@see self::go()}     创建受管理的协程
 * - {@see self::sleep()}  真正让出执行权的休眠（不阻塞其它协程）
 * - {@see self::delay()}  一次性 / 周期性定时器
 * - {@see self::park()}   供 Channel、WaitGroup、Semaphore 等原语挂起与唤醒协程
 *
 * ```php
 * $scheduler = new Scheduler();
 * $a = $scheduler->go(function () use ($scheduler) {
 *     $scheduler->sleep(0.1);
 *     return 'a';
 * });
 * $b = $scheduler->go(fn() => 'b');
 * $scheduler->run();          // 驱动事件循环直到无事可做
 * [$a->result(), $b->result()];
 * ```
 */
final class Scheduler
{
    /**
     * 当前正在驱动事件循环的调度器
     */
    private static ?self $current = null;

    /**
     * 进程级默认调度器
     */
    private static ?self $default = null;

    /**
     * 就绪回调队列
     *
     * @var SplQueue<Closure>
     */
    private SplQueue $ready;

    private TimerQueue $timers;

    private int $timerSequence = 0;

    private bool $running = false;

    /**
     * Fiber => Coroutine 归属关系（弱引用，协程结束后自动回收）
     *
     * @var WeakMap<Fiber, Coroutine>
     */
    private WeakMap $owned;

    private int $tickCount = 0;

    private int $spawnedCount = 0;

    /**
     * 已失败但结果尚未被消费的协程
     *
     * @var Coroutine[]
     */
    private array $failures = [];

    /**
     * 定时器 / defer 回调抛出异常时的处理器，为 null 时向外抛出
     */
    private ?Closure $errorHandler = null;

    public function __construct()
    {
        $this->ready = new SplQueue();
        $this->timers = new TimerQueue();
        $this->owned = new WeakMap();
    }

    /**
     * 单调时钟当前值（秒）
     *
     * 使用 hrtime() 而非 microtime()，不受系统时间调整（NTP）影响。
     */
    public static function now(): float
    {
        return hrtime(true) / 1_000_000_000;
    }

    /**
     * 获取进程级默认调度器
     */
    public static function default(): self
    {
        return self::$default ??= new self();
    }

    /**
     * 获取当前正在运行的调度器（不在事件循环内时返回 null）
     */
    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * 当前是否处于某个受调度器管理的协程内部
     */
    public static function inCoroutine(): bool
    {
        $fiber = Fiber::getCurrent();

        return $fiber !== null && self::$current !== null && self::$current->ownsFiber($fiber);
    }

    /**
     * 重置默认调度器（主要用于测试隔离）
     */
    public static function resetDefault(): void
    {
        self::$default = null;
    }

    /**
     * 事件循环是否正在运行
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * 指定 Fiber 是否由本调度器创建
     */
    public function ownsFiber(Fiber $fiber): bool
    {
        return isset($this->owned[$fiber]);
    }

    /**
     * 获取当前正在执行的协程句柄
     */
    public function currentCoroutine(): ?Coroutine
    {
        $fiber = Fiber::getCurrent();

        if ($fiber === null || !isset($this->owned[$fiber])) {
            return null;
        }

        return $this->owned[$fiber];
    }

    /**
     * 创建并调度一个协程
     *
     * @param callable    $task 协程主体
     * @param string|null $name 可选名称，便于调试
     */
    public function go(callable $task, ?string $name = null): Coroutine
    {
        $coroutine = new Coroutine(
            $task instanceof Closure ? $task : Closure::fromCallable($task),
            $this,
            $name
        );

        $this->owned[$coroutine->fiber()] = $coroutine;
        $this->spawnedCount++;

        $this->ready->enqueue(static function () use ($coroutine): void {
            $coroutine->start();
        });

        return $coroutine;
    }

    /**
     * 批量创建协程
     *
     * @param  iterable<array-key, callable> $tasks
     * @return array<array-key, Coroutine>
     */
    public function goAll(iterable $tasks): array
    {
        $coroutines = [];

        foreach ($tasks as $key => $task) {
            $coroutines[$key] = $this->go($task, is_string($key) ? $key : null);
        }

        return $coroutines;
    }

    /**
     * 向就绪队列投递一个回调
     *
     * @internal 供 Suspension 等内部组件使用
     */
    public function enqueue(Closure $callback): void
    {
        $this->ready->enqueue($callback);
    }

    /**
     * 在下一个事件循环 tick 执行回调
     */
    public function defer(callable $callback): void
    {
        $this->ready->enqueue($callback instanceof Closure ? $callback : Closure::fromCallable($callback));
    }

    /**
     * 注册一次性定时器
     *
     * @param float $seconds 延迟秒数
     */
    public function delay(float $seconds, callable $callback): Timer
    {
        return $this->createTimer($seconds, $callback, null);
    }

    /**
     * 注册周期性定时器
     *
     * @param float $seconds 间隔秒数（必须大于 0）
     */
    public function repeat(float $seconds, callable $callback): Timer
    {
        if ($seconds <= 0) {
            throw new FiberException('周期性定时器的间隔必须大于 0 秒');
        }

        return $this->createTimer($seconds, $callback, $seconds);
    }

    /**
     * 挂起当前协程，返回可用于唤醒它的句柄
     *
     * @throws FiberException 当前不在受本调度器管理的协程内
     */
    public function park(): Suspension
    {
        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            throw new FiberException(
                '当前不在协程内，无法挂起。请通过 Fibers::async() / Scheduler::go() 创建协程后再调用。'
            );
        }

        if (!isset($this->owned[$fiber])) {
            throw new FiberException(
                '当前 Fiber 不受该调度器管理，无法挂起。请使用同一个 Scheduler 创建协程。'
            );
        }

        $suspension = new Suspension($this, $fiber);
        $this->owned[$fiber]->setSuspension($suspension);

        return $suspension;
    }

    /**
     * 协程休眠（让出执行权，不阻塞其它协程）
     */
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            $this->yieldNow();

            return;
        }

        $suspension = $this->park();
        $timer = $this->delay($seconds, static function () use ($suspension): void {
            $suspension->resume();
        });

        try {
            $suspension->suspend();
        } finally {
            $timer->cancel();
        }
    }

    /**
     * 主动让出一次执行权，把机会交给其它就绪协程
     */
    public function yieldNow(): void
    {
        $suspension = $this->park();

        $this->ready->enqueue(static function () use ($suspension): void {
            $suspension->resume();
        });

        $suspension->suspend();
    }

    /**
     * 等待协程完成并取回结果
     *
     * @param  Coroutine|array<array-key, Coroutine> $coroutine
     * @return mixed 单个协程返回其结果；数组返回与键对应的结果数组
     * @throws Throwable
     */
    public function await(Coroutine|array $coroutine, ?float $timeout = null): mixed
    {
        if (is_array($coroutine)) {
            $results = [];

            foreach ($coroutine as $key => $item) {
                $results[$key] = $item->join($timeout);
            }

            return $results;
        }

        return $coroutine->join($timeout);
    }

    /**
     * 以截止时间运行一个任务：超时后会在协程的下一个挂起点真正中断它
     *
     * 与「跑完再比较耗时」的事后断言不同，这里超时的任务会被取消，
     * 不会继续占用资源，也不会被上层误判为可重试的失败。
     *
     * @throws TimeoutException 超时
     * @throws Throwable        任务自身抛出的异常
     */
    public function withTimeout(callable $task, float $timeout): mixed
    {
        $coroutine = $this->go($task);

        $timer = $this->delay($timeout, static function () use ($coroutine, $timeout): void {
            $coroutine->cancel(sprintf('任务执行超过 %.3f 秒，已被取消', $timeout));
        });

        try {
            return $this->await($coroutine);
        } catch (CancelledException $e) {
            if ($coroutine->isCancelRequested()) {
                throw new TimeoutException(
                    sprintf('任务执行超过 %.3f 秒', $timeout),
                    $e->getCode(),
                    $e
                );
            }

            throw $e;
        } finally {
            $timer->cancel();
        }
    }

    /**
     * 驱动事件循环
     *
     * @param callable|null $until 停止条件，返回 true 时退出；为 null 时运行到无事可做
     * @throws FiberException 重入调用
     */
    public function run(?callable $until = null): void
    {
        if ($this->running) {
            throw new FiberException('Scheduler::run() 不可重入，请使用 defer() / go() 在事件循环内调度任务');
        }

        $previous = self::$current;
        self::$current = $this;
        $this->running = true;

        try {
            while (true) {
                if ($until !== null && $until()) {
                    return;
                }

                $this->expireTimers();

                if (!$this->ready->isEmpty()) {
                    $this->drainReady();

                    continue;
                }

                $next = $this->timers->peek();

                if ($next === null) {
                    return;
                }

                $delay = $next->at() - self::now();

                if ($delay > 0) {
                    // 无任何就绪任务，等待最近的定时器到期：这是真正的空闲等待而非轮询
                    usleep((int) ceil($delay * 1_000_000));
                }
            }
        } finally {
            $this->running = false;
            self::$current = $previous;
        }
    }

    /**
     * 执行一批任务并返回结果（键与输入保持一致）
     *
     * @param  iterable<array-key, callable> $tasks
     * @return array<array-key, mixed>
     */
    public function runAll(iterable $tasks): array
    {
        $coroutines = $this->goAll($tasks);

        $this->run(static function () use ($coroutines): bool {
            foreach ($coroutines as $coroutine) {
                if (!$coroutine->isFinished()) {
                    return false;
                }
            }

            return true;
        });

        $results = [];

        foreach ($coroutines as $key => $coroutine) {
            $results[$key] = $coroutine->result();
        }

        return $results;
    }

    /**
     * 设置定时器 / defer 回调的异常处理器
     *
     * 未设置时，异常会直接从 {@see self::run()} 向外抛出。
     */
    public function setErrorHandler(?callable $handler): void
    {
        $this->errorHandler = $handler === null
            ? null
            : ($handler instanceof Closure ? $handler : Closure::fromCallable($handler));
    }

    /**
     * 获取「已失败且结果从未被读取」的协程异常列表
     *
     * @return Throwable[]
     */
    public function unhandledErrors(): array
    {
        $this->failures = array_values(array_filter(
            $this->failures,
            static fn(Coroutine $coroutine): bool => !$coroutine->isHandled()
        ));

        return array_map(
            static fn(Coroutine $coroutine): Throwable => $coroutine->error() ?? new FiberException('unknown'),
            $this->failures
        );
    }

    /**
     * 协程结束回调
     *
     * @internal 仅供 Coroutine 调用
     */
    public function onCoroutineFinished(Coroutine $coroutine): void
    {
        if ($coroutine->error() === null) {
            return;
        }

        $this->failures[] = $coroutine;

        // 只保留最近的失败记录，避免长驻进程内存无限增长
        if (count($this->failures) > 128) {
            $this->failures = array_slice($this->failures, -128);
        }
    }

    /**
     * 调度器运行统计
     *
     * @return array{ticks:int, spawned:int, ready:int, timers:int, running:bool}
     */
    public function stats(): array
    {
        return [
            'ticks' => $this->tickCount,
            'spawned' => $this->spawnedCount,
            'ready' => $this->ready->count(),
            'timers' => $this->timers->count(),
            'running' => $this->running,
        ];
    }

    /**
     * 丢弃所有待执行的回调与定时器
     */
    public function clear(): void
    {
        $this->ready = new SplQueue();
        $this->timers->clear();
        $this->failures = [];
    }

    private function createTimer(float $seconds, callable $callback, ?float $interval): Timer
    {
        $sequence = ++$this->timerSequence;
        $queue = $this->timers;

        $timer = new Timer(
            'timer#' . $sequence,
            self::now() + max(0.0, $seconds),
            $sequence,
            $callback instanceof Closure ? $callback : Closure::fromCallable($callback),
            $interval,
            static function () use ($queue): void {
                $queue->markStale();
            }
        );

        $this->timers->insert($timer);

        return $timer;
    }

    /**
     * 将所有到期定时器搬运到就绪队列
     */
    private function expireTimers(): void
    {
        $now = self::now();

        while (($timer = $this->timers->peek()) !== null && $timer->at() <= $now) {
            $this->timers->extract();

            if ($timer->isCancelled()) {
                continue;
            }

            if ($timer->isPeriodic()) {
                $timer->reschedule($now);
                $this->timers->insert($timer);
            }

            $this->ready->enqueue(static function () use ($timer): void {
                $timer->fire();
            });
        }
    }

    /**
     * 执行当前批次的就绪回调
     *
     * 只处理进入本轮时的快照数量，新产生的回调留到下一轮，
     * 从而保证定时器不会被持续自我唤醒的协程饿死。
     */
    private function drainReady(): void
    {
        $batch = $this->ready->count();

        for ($i = 0; $i < $batch && !$this->ready->isEmpty(); $i++) {
            $callback = $this->ready->dequeue();
            $this->tickCount++;

            try {
                $callback();
            } catch (Throwable $e) {
                if ($this->errorHandler === null) {
                    throw $e;
                }

                ($this->errorHandler)($e);
            }
        }
    }
}
