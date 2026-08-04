<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Closure;
use Fiber;
use Throwable;

/**
 * 协程句柄
 *
 * 由 {@see Scheduler::go()} 创建，代表一个受调度器管理的执行单元。
 * 支持 join（等待结果）、cancel（在下一个挂起点中断）与状态查询。
 */
final class Coroutine
{
    private static int $counter = 0;

    private readonly Fiber $fiber;

    private CoroutineState $state = CoroutineState::Pending;

    private mixed $result = null;

    private ?Throwable $error = null;

    /**
     * @var Suspension[] 等待本协程结束的其它协程
     */
    private array $joiners = [];

    /**
     * 当前挂起句柄（用于取消时把异常注入挂起点）
     */
    private ?Suspension $suspension = null;

    /**
     * 结果或异常是否已被消费
     */
    private bool $handled = false;

    /**
     * 是否收到过取消请求
     */
    private bool $cancelRequested = false;

    public readonly string $id;

    public function __construct(
        Closure $task,
        private readonly Scheduler $scheduler,
        ?string $name = null,
    ) {
        $this->id = $name ?? 'co#' . (++self::$counter);

        $this->fiber = new Fiber(function () use ($task): void {
            try {
                $this->result = $task();
                $this->state = CoroutineState::Completed;
            } catch (CancelledException $e) {
                $this->error = $e;
                $this->state = CoroutineState::Cancelled;
            } catch (Throwable $e) {
                $this->error = $e;
                $this->state = CoroutineState::Failed;
            } finally {
                $this->suspension = null;
                $this->wakeJoiners();
            }
        });
    }

    /**
     * 底层 Fiber 实例
     *
     * @internal 仅供 Scheduler 登记归属关系
     */
    public function fiber(): Fiber
    {
        return $this->fiber;
    }

    /**
     * 启动协程
     *
     * @internal 仅供 Scheduler 调用
     */
    public function start(): void
    {
        if ($this->state !== CoroutineState::Pending) {
            return;
        }

        if ($this->cancelRequested) {
            $this->state = CoroutineState::Cancelled;
            $this->error = new CancelledException(sprintf('协程 %s 在启动前已被取消', $this->id));
            $this->wakeJoiners();

            return;
        }

        $this->state = CoroutineState::Running;
        $this->fiber->start();
    }

    /**
     * 记录当前挂起句柄
     *
     * @internal 仅供 Scheduler 调用
     */
    public function setSuspension(?Suspension $suspension): void
    {
        $this->suspension = $suspension;
    }

    /**
     * 等待协程结束并返回结果
     *
     * 在协程内部调用会挂起当前协程；在协程外部调用会驱动调度器直到本协程结束。
     *
     * @throws Throwable 协程执行期间抛出的异常
     */
    public function join(?float $timeout = null): mixed
    {
        if (!$this->state->isFinished()) {
            $currentFiber = Fiber::getCurrent();

            if ($currentFiber !== null && $this->scheduler->ownsFiber($currentFiber)) {
                $suspension = $this->scheduler->park();
                $this->joiners[] = $suspension;

                $timer = null;
                if ($timeout !== null) {
                    $timer = $this->scheduler->delay(
                        $timeout,
                        fn() => $suspension->throw(new TimeoutException(
                            sprintf('等待协程 %s 超时（%.3fs）', $this->id, $timeout)
                        ))
                    );
                }

                try {
                    $suspension->suspend();
                } finally {
                    $timer?->cancel();
                }
            } else {
                $deadline = $timeout === null ? null : Scheduler::now() + $timeout;
                $this->scheduler->run(
                    fn(): bool => $this->state->isFinished()
                        || ($deadline !== null && Scheduler::now() >= $deadline)
                );

                if (!$this->state->isFinished()) {
                    if ($timeout !== null) {
                        throw new TimeoutException(
                            sprintf('等待协程 %s 超时（%.3fs）', $this->id, $timeout)
                        );
                    }

                    // 事件循环已无任何可执行任务，但协程仍未结束：只可能是相互等待造成的死锁
                    throw new DeadlockException(
                        sprintf('等待协程 %s 时事件循环已无可执行任务，可能存在死锁', $this->id)
                    );
                }
            }
        }

        return $this->result();
    }

    /**
     * 获取执行结果（协程必须已结束）
     *
     * @throws Throwable
     */
    public function result(): mixed
    {
        $this->handled = true;

        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->result;
    }

    /**
     * 请求取消协程
     *
     * 若协程尚未启动则不会执行；若正挂起于某个等待点，则在该点抛出
     * {@see CancelledException}；若正在运行，仅置位标记，由
     * {@see self::throwIfCancelled()} 或下一个挂起点感知。
     */
    public function cancel(?string $reason = null): void
    {
        if ($this->state->isFinished()) {
            return;
        }

        $this->cancelRequested = true;
        $exception = new CancelledException(
            $reason ?? sprintf('协程 %s 已被取消', $this->id)
        );

        $this->suspension?->throw($exception);
    }

    /**
     * 是否收到过取消请求
     */
    public function isCancelRequested(): bool
    {
        return $this->cancelRequested;
    }

    /**
     * 若已收到取消请求则立即抛出异常（供长循环任务主动检查）
     *
     * @throws CancelledException
     */
    public function throwIfCancelled(): void
    {
        if ($this->cancelRequested && !$this->state->isFinished()) {
            throw new CancelledException(sprintf('协程 %s 已被取消', $this->id));
        }
    }

    public function state(): CoroutineState
    {
        return $this->state;
    }

    public function isFinished(): bool
    {
        return $this->state->isFinished();
    }

    public function isSuccessful(): bool
    {
        return $this->state === CoroutineState::Completed;
    }

    /**
     * 获取失败原因（未失败时为 null）
     */
    public function error(): ?Throwable
    {
        return $this->error;
    }

    /**
     * 异常是否已被 join()/result() 消费
     *
     * @internal 供 Scheduler 统计未处理异常
     */
    public function isHandled(): bool
    {
        return $this->handled;
    }

    private function wakeJoiners(): void
    {
        $joiners = $this->joiners;
        $this->joiners = [];

        foreach ($joiners as $joiner) {
            $joiner->resume();
        }

        $this->scheduler->onCoroutineFinished($this);
    }
}
