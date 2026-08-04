<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Kode\Fibers\Exceptions\FiberException;

/**
 * 计数信号量式的任务等待组
 *
 * 与「一次性提交全部任务」的 concurrent() 不同，WaitGroup 允许边跑边加：
 *
 * ```php
 * $wg = new WaitGroup();
 *
 * foreach ($urls as $url) {
 *     $wg->add();
 *     $scheduler->go(function () use ($wg, $url) {
 *         try {
 *             fetch($url);
 *         } finally {
 *             $wg->done();
 *         }
 *     });
 * }
 *
 * $wg->wait();   // 等待全部完成
 * ```
 */
final class WaitGroup
{
    private int $counter = 0;

    /**
     * @var Suspension[]
     */
    private array $waiters = [];

    public function __construct(private readonly ?Scheduler $scheduler = null)
    {
    }

    /**
     * 增加计数
     *
     * @throws FiberException $delta 不为正数
     */
    public function add(int $delta = 1): void
    {
        if ($delta <= 0) {
            throw new FiberException('WaitGroup::add() 的增量必须为正数');
        }

        $this->counter += $delta;
    }

    /**
     * 完成一个任务，计数减一；归零时唤醒所有等待者
     *
     * @throws FiberException 计数已为 0 时重复调用
     */
    public function done(): void
    {
        if ($this->counter <= 0) {
            throw new FiberException('WaitGroup::done() 调用次数超过 add() 的总数');
        }

        $this->counter--;

        if ($this->counter === 0) {
            $this->releaseAll();
        }
    }

    /**
     * 等待计数归零
     *
     * @param  float|null $timeout 超时秒数，null 表示不限
     * @throws TimeoutException 超时
     */
    public function wait(?float $timeout = null): void
    {
        if ($this->counter === 0) {
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
                    sprintf('WaitGroup 等待超时（%.3fs），仍有 %d 个任务未完成', $timeout, $this->counter)
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
     * 当前未完成的任务数
     */
    public function count(): int
    {
        return $this->counter;
    }

    /**
     * 是否已全部完成
     */
    public function isDone(): bool
    {
        return $this->counter === 0;
    }

    private function releaseAll(): void
    {
        $waiters = $this->waiters;
        $this->waiters = [];

        foreach ($waiters as $waiter) {
            $waiter->resume();
        }
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
                'WaitGroup::wait() 需要在运行中的事件循环内调用，请在 Fibers::async() / Scheduler::go() 创建的协程中使用。'
            );
        }

        return $scheduler;
    }
}
