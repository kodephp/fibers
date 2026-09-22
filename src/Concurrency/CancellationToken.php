<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Closure;
use Throwable;

/**
 * 取消令牌
 *
 * 只读的取消信号载体，可安全传递给下游任务。取消状态由创建它的
 * {@see CancellationTokenSource} 控制，令牌本身无法触发取消。
 *
 * ```php
 * $source = CancellationTokenSource::withTimeout(2.0);
 *
 * $scheduler->go(function () use ($source) {
 *     foreach ($rows as $row) {
 *         $source->token()->throwIfCancelled();   // 协作式检查点
 *         process($row);
 *     }
 * });
 * ```
 */
final class CancellationToken
{
    private bool $cancelled = false;

    private ?Throwable $reason = null;

    /**
     * @var array<int, Closure> 取消时触发的回调
     */
    private array $callbacks = [];

    private int $callbackSequence = 0;

    /**
     * 墙上时钟截止点（单调秒），到点后由下一次观察动作落实取消
     */
    private ?float $deadlineAt = null;

    private ?Throwable $deadlineReason = null;

    /**
     * @internal 请通过 CancellationTokenSource 创建
     */
    public function __construct()
    {
    }

    /**
     * 永不取消的令牌
     */
    public static function none(): self
    {
        return new self();
    }

    public function isCancelled(): bool
    {
        $this->settleDeadline();

        return $this->cancelled;
    }

    /**
     * 登记一个墙上时钟截止点
     *
     * 用于「创建令牌时不在事件循环里」的场景：那种情况下注册定时器等于永不触发
     * （没人驱动 run()），而闭包又会把令牌源钉在进程级默认调度器上——常驻 worker 里
     * 就是无界泄漏。改为按单调时钟惰性判定：到点后由下一次观察动作落实。
     *
     * @internal 仅供 CancellationTokenSource 使用
     */
    public function armDeadline(float $deadlineAt, Throwable $reason): void
    {
        if ($this->cancelled) {
            return;
        }

        $this->deadlineAt = $deadlineAt;
        $this->deadlineReason = $reason;
    }

    /**
     * 把已到期的墙上时钟截止落实为取消（并触发订阅回调）
     */
    private function settleDeadline(): void
    {
        if ($this->cancelled || $this->deadlineAt === null || Scheduler::now() < $this->deadlineAt) {
            return;
        }

        $reason = $this->deadlineReason ?? new CancelledException('操作已被取消');
        $this->deadlineAt = null;
        $this->deadlineReason = null;

        $this->cancel($reason);
    }

    /**
     * 取消原因（未取消时为 null）
     */
    public function reason(): ?Throwable
    {
        $this->settleDeadline();

        return $this->reason;
    }

    /**
     * 若已取消则抛出异常
     *
     * @throws CancelledException
     */
    public function throwIfCancelled(): void
    {
        $this->settleDeadline();

        if (!$this->cancelled) {
            return;
        }

        $reason = $this->reason;

        if ($reason instanceof CancelledException) {
            throw $reason;
        }

        throw new CancelledException(
            $reason?->getMessage() ?? '操作已被取消',
            (int) ($reason?->getCode() ?? 0),
            $reason
        );
    }

    /**
     * 注册取消回调；若令牌已取消则立即执行
     *
     * @return int 订阅 ID，可用于 {@see self::unsubscribe()}
     */
    public function subscribe(callable $callback): int
    {
        $closure = $callback instanceof Closure ? $callback : Closure::fromCallable($callback);

        $this->settleDeadline();

        if ($this->cancelled) {
            $closure($this->reason);

            return -1;
        }

        $id = ++$this->callbackSequence;
        $this->callbacks[$id] = $closure;

        return $id;
    }

    /**
     * 取消订阅
     */
    public function unsubscribe(int $id): void
    {
        unset($this->callbacks[$id]);
    }

    /**
     * 触发取消
     *
     * @internal 仅供 CancellationTokenSource 调用
     */
    public function cancel(Throwable $reason): void
    {
        if ($this->cancelled) {
            return;
        }

        $this->deadlineAt = null;
        $this->deadlineReason = null;
        $this->cancelled = true;
        $this->reason = $reason;

        $callbacks = $this->callbacks;
        $this->callbacks = [];

        foreach ($callbacks as $callback) {
            try {
                $callback($reason);
            } catch (Throwable) {
                // 取消回调自身的异常不应影响其它订阅者
            }
        }
    }
}
