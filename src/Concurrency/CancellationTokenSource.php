<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Throwable;

/**
 * 取消令牌源
 *
 * 持有取消控制权，通过 {@see self::token()} 把只读令牌分发给下游。
 * 支持超时自动取消与父子级联取消。
 */
final class CancellationTokenSource
{
    private readonly CancellationToken $token;

    private ?Timer $timer = null;

    /**
     * @var array<int, array{0: CancellationToken, 1: int}> 已注册的父令牌订阅
     */
    private array $linked = [];

    public function __construct()
    {
        $this->token = new CancellationToken();
    }

    /**
     * 创建一个在指定秒数后自动取消的令牌源
     */
    public static function withTimeout(float $seconds, ?Scheduler $scheduler = null): self
    {
        $source = new self();
        $scheduler ??= Scheduler::current() ?? Scheduler::default();

        $source->timer = $scheduler->delay($seconds, static function () use ($source, $seconds): void {
            $source->cancel(new TimeoutException(sprintf('操作超时（%.3fs）', $seconds)));
        });

        return $source;
    }

    /**
     * 创建一个与若干父令牌级联的令牌源：任一父令牌取消，本令牌即取消
     */
    public static function linkedWith(CancellationToken ...$parents): self
    {
        $source = new self();

        foreach ($parents as $parent) {
            $id = $parent->subscribe(static function (?Throwable $reason) use ($source): void {
                $source->cancel($reason instanceof Throwable ? $reason : new CancelledException('父级操作已被取消'));
            });

            if ($id > 0) {
                $source->linked[] = [$parent, $id];
            }
        }

        return $source;
    }

    /**
     * 获取只读令牌
     */
    public function token(): CancellationToken
    {
        return $this->token;
    }

    /**
     * 触发取消
     */
    public function cancel(Throwable|string|null $reason = null): void
    {
        if ($this->token->isCancelled()) {
            return;
        }

        $exception = match (true) {
            $reason instanceof Throwable => $reason,
            is_string($reason) => new CancelledException($reason),
            default => new CancelledException('操作已被取消'),
        };

        $this->dispose();
        $this->token->cancel($exception);
    }

    public function isCancelled(): bool
    {
        return $this->token->isCancelled();
    }

    /**
     * 释放定时器与父令牌订阅，避免长驻进程泄漏
     */
    public function dispose(): void
    {
        $this->timer?->cancel();
        $this->timer = null;

        foreach ($this->linked as [$parent, $id]) {
            $parent->unsubscribe($id);
        }

        $this->linked = [];
    }
}
