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
        return $this->cancelled;
    }

    /**
     * 取消原因（未取消时为 null）
     */
    public function reason(): ?Throwable
    {
        return $this->reason;
    }

    /**
     * 若已取消则抛出异常
     *
     * @throws CancelledException
     */
    public function throwIfCancelled(): void
    {
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
