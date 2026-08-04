<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Kode\Fibers\Exceptions\FiberException;
use Throwable;

/**
 * 计数信号量
 *
 * 用于限制同时进入某段逻辑的协程数量，是实现「真并发上限」的正确方式
 * （相比把任务切成一块块串行执行，信号量能让空出的名额被立刻复用）。
 *
 * ```php
 * $sem = new Semaphore(3);           // 最多 3 个并发
 *
 * foreach ($items as $item) {
 *     $scheduler->go(fn() => $sem->run(fn() => handle($item)));
 * }
 * ```
 */
final class Semaphore
{
    /**
     * 剩余可用名额
     */
    private int $permits;

    /**
     * @var Suspension[] 等待名额的协程
     */
    private array $waiters = [];

    /**
     * @param int $permits 并发上限（必须为正）
     */
    public function __construct(int $permits, private readonly ?Scheduler $scheduler = null)
    {
        if ($permits <= 0) {
            throw new FiberException('Semaphore 的并发上限必须大于 0');
        }

        $this->permits = $permits;
    }

    /**
     * 获取一个名额，名额耗尽时挂起当前协程
     *
     * @throws TimeoutException 超时
     */
    public function acquire(?float $timeout = null): void
    {
        if ($this->permits > 0) {
            $this->permits--;

            return;
        }

        $scheduler = $this->resolveScheduler();
        $suspension = $scheduler->park();
        $this->waiters[] = $suspension;

        $timer = null;

        if ($timeout !== null) {
            $timer = $scheduler->delay($timeout, function () use ($suspension, $timeout): void {
                $this->removeWaiter($suspension);
                $suspension->throw(new TimeoutException(
                    sprintf('获取信号量超时（%.3fs）', $timeout)
                ));
            });
        }

        try {
            $suspension->suspend();
        } finally {
            $timer?->cancel();
        }
    }

    /**
     * 非阻塞地尝试获取名额
     */
    public function tryAcquire(): bool
    {
        if ($this->permits > 0) {
            $this->permits--;

            return true;
        }

        return false;
    }

    /**
     * 归还一个名额
     */
    public function release(): void
    {
        while ($this->waiters !== []) {
            $suspension = array_shift($this->waiters);

            if ($suspension->isPending()) {
                // 名额直接移交给等待者，不回到 permits，避免被后来者插队
                $suspension->resume();

                return;
            }
        }

        $this->permits++;
    }

    /**
     * 在名额保护下执行任务，无论成功失败都会归还名额
     *
     * @throws Throwable
     */
    public function run(callable $task, ?float $timeout = null): mixed
    {
        $this->acquire($timeout);

        try {
            return $task();
        } finally {
            $this->release();
        }
    }

    /**
     * 剩余可用名额
     */
    public function available(): int
    {
        return $this->permits;
    }

    /**
     * 正在等待名额的协程数
     */
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
                'Semaphore 名额耗尽时需要挂起协程，请在运行中的事件循环内使用（Fibers::async() / Scheduler::go()）。'
            );
        }

        return $scheduler;
    }
}
