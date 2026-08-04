<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Fiber;
use Kode\Fibers\Exceptions\FiberException;
use Throwable;

/**
 * 协程挂起句柄
 *
 * 通过 {@see Scheduler::park()} 获取。调用方先拿到句柄、把它登记到等待队列，
 * 再调用 {@see self::suspend()} 让出执行权；其它协程或定时器通过
 * {@see self::resume()} / {@see self::throw()} 将其唤醒。
 *
 * 唤醒动作总是投递到调度器的就绪队列，不会在当前调用栈上直接 resume，
 * 从而避免协程之间产生不必要的嵌套。
 */
final class Suspension
{
    /**
     * 是否仍在等待唤醒
     */
    private bool $pending = true;

    /**
     * 是否已真正执行 Fiber::suspend()
     */
    private bool $suspended = false;

    /**
     * 在挂起前就被唤醒时暂存的返回值
     */
    private mixed $earlyValue = null;

    /**
     * 在挂起前就被唤醒时暂存的异常
     */
    private ?Throwable $earlyError = null;

    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly Fiber $fiber,
    ) {
    }

    /**
     * 让出执行权，直到被 resume() / throw() 唤醒
     *
     * @throws Throwable 由 throw() 注入的异常
     */
    public function suspend(): mixed
    {
        if (!$this->pending) {
            // 尚未挂起就已被唤醒，直接返回结果，避免永久阻塞
            if ($this->earlyError !== null) {
                throw $this->earlyError;
            }

            return $this->earlyValue;
        }

        if (Fiber::getCurrent() !== $this->fiber) {
            throw new FiberException('Suspension::suspend() 必须在创建它的协程内部调用');
        }

        $this->suspended = true;

        return Fiber::suspend();
    }

    /**
     * 以一个返回值唤醒协程
     */
    public function resume(mixed $value = null): void
    {
        if (!$this->pending) {
            return;
        }

        $this->pending = false;
        $this->earlyValue = $value;

        if (!$this->suspended) {
            return;
        }

        $fiber = $this->fiber;
        $this->scheduler->enqueue(static function () use ($fiber, $value): void {
            if ($fiber->isTerminated() || !$fiber->isSuspended()) {
                return;
            }

            $fiber->resume($value);
        });
    }

    /**
     * 以一个异常唤醒协程
     */
    public function throw(Throwable $error): void
    {
        if (!$this->pending) {
            return;
        }

        $this->pending = false;
        $this->earlyError = $error;

        if (!$this->suspended) {
            return;
        }

        $fiber = $this->fiber;
        $this->scheduler->enqueue(static function () use ($fiber, $error): void {
            if ($fiber->isTerminated() || !$fiber->isSuspended()) {
                return;
            }

            $fiber->throw($error);
        });
    }

    /**
     * 是否仍在等待唤醒
     */
    public function isPending(): bool
    {
        return $this->pending;
    }
}
