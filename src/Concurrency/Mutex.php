<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Fiber;
use Kode\Fibers\Exceptions\FiberException;
use Throwable;

/**
 * 协程互斥锁
 *
 * 基于调度器的挂起 / 唤醒实现，不做任何自旋等待——
 * 在单线程协作式调度下，自旋等待只会导致必然死锁。
 *
 * ```php
 * $mutex = new Mutex();
 * $mutex->run(function () use (&$shared) {
 *     $shared['counter']++;
 * });
 * ```
 */
final class Mutex
{
    private bool $locked = false;

    /**
     * 持锁协程对应的 Fiber，用于识别重入
     */
    private ?Fiber $owner = null;

    /**
     * @var Suspension[]
     */
    private array $waiters = [];

    public function __construct(private readonly ?Scheduler $scheduler = null)
    {
    }

    /**
     * 加锁；已被占用时挂起当前协程直到锁释放
     *
     * @throws TimeoutException 超时
     * @throws FiberException   同一协程重复加锁（不支持重入）
     */
    public function lock(?float $timeout = null): void
    {
        $current = Fiber::getCurrent();

        if ($this->locked && $current !== null && $this->owner === $current) {
            throw new FiberException('Mutex 不支持重入，同一协程不能重复加锁');
        }

        if (!$this->locked) {
            $this->locked = true;
            $this->owner = $current;

            return;
        }

        $scheduler = $this->resolveScheduler();
        $suspension = $scheduler->park();
        $this->waiters[] = $suspension;

        $timer = null;

        if ($timeout !== null) {
            $timer = $scheduler->delay($timeout, function () use ($suspension, $timeout): void {
                $this->removeWaiter($suspension);
                $suspension->throw(new TimeoutException(sprintf('获取互斥锁超时（%.3fs）', $timeout)));
            });
        }

        try {
            $suspension->suspend();
        } finally {
            $timer?->cancel();
        }

        $this->owner = $current;
    }

    /**
     * 非阻塞地尝试加锁
     */
    public function tryLock(): bool
    {
        if ($this->locked) {
            return false;
        }

        $this->locked = true;
        $this->owner = Fiber::getCurrent();

        return true;
    }

    /**
     * 解锁
     */
    public function unlock(): void
    {
        if (!$this->locked) {
            return;
        }

        $current = Fiber::getCurrent();

        // 仅允许持锁协程解锁，避免任意协程误解除他人持有的锁。
        // 持锁者为某个 Fiber（owner !== null）而当前上下文并非该 Fiber（含 null）时拒绝。
        if ($this->owner !== null && $current !== $this->owner) {
            throw new FiberException('只有持锁协程可以解锁');
        }

        $this->owner = null;

        while ($this->waiters !== []) {
            $suspension = array_shift($this->waiters);

            if ($suspension->isPending()) {
                // 锁直接移交给下一个等待者，保持 FIFO 公平性
                $suspension->resume();

                return;
            }
        }

        $this->locked = false;
    }

    /**
     * 在锁保护下执行任务，无论成功失败都会解锁
     *
     * @throws Throwable
     */
    public function run(callable $task, ?float $timeout = null): mixed
    {
        $this->lock($timeout);

        try {
            return $task();
        } finally {
            $this->unlock();
        }
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function waiting(): int
    {
        return count($this->waiters);
    }

    private function removeWaiter(Suspension $suspension): void
    {
        foreach ($this->waiters as $index => $waiter) {
            if ($waiter === $suspension) {
                unset($this->waiters[$index]);

                return;
            }
        }
    }

    private function resolveScheduler(): Scheduler
    {
        $scheduler = $this->scheduler ?? Scheduler::current() ?? Scheduler::default();

        if (!$scheduler->isRunning()) {
            throw new FiberException(
                'Mutex 竞争时需要挂起协程，请在运行中的事件循环内使用（Fibers::async() / Scheduler::go()）。'
            );
        }

        return $scheduler;
    }
}
