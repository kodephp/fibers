<?php

declare(strict_types=1);

namespace Kode\Fibers\Core;

use Kode\Fibers\Contracts\CircuitBreakerInterface;

/**
 * 断路器实现
 *
 * 用于在连续失败时快速失败，避免雪崩。
 * 状态机：CLOSED -> OPEN -> HALF_OPEN -> CLOSED（成功）/ OPEN（失败）。
 */
class CircuitBreaker implements CircuitBreakerInterface
{
    /** 状态：已闭合（正常放行） */
    public const string STATE_CLOSED = 'closed';
    /** 状态：已断开（快速失败） */
    public const string STATE_OPEN = 'open';
    /** 状态：半开（试探性放行） */
    public const string STATE_HALF_OPEN = 'half_open';

    protected string $state = self::STATE_CLOSED;
    protected int $failureCount = 0;
    protected ?float $openedAt = null;
    protected int $halfOpenCalls = 0;

    /**
     * @param int $failureThreshold 失败阈值
     * @param float $recoveryTimeout 熔断恢复窗口（秒）
     * @param int $halfOpenMaxCalls 半开状态允许请求数
     */
    public function __construct(
        protected int $failureThreshold = 5,
        protected float $recoveryTimeout = 3.0,
        protected int $halfOpenMaxCalls = 1
    ) {
    }

    #[\Override]
    public function allowRequest(): bool
    {
        // 闭合状态：直接放行
        if ($this->state === self::STATE_CLOSED) {
            return true;
        }

        // 断开状态：检查恢复窗口是否到期
        if ($this->state === self::STATE_OPEN) {
            $openedAt = $this->openedAt ?? microtime(true);
            if ((microtime(true) - $openedAt) >= $this->recoveryTimeout) {
                // 恢复窗口到期，转为半开状态
                $this->state = self::STATE_HALF_OPEN;
                $this->halfOpenCalls = 0;
            } else {
                return false;
            }
        }

        // 半开状态：限制试探请求数
        if ($this->state === self::STATE_HALF_OPEN) {
            if ($this->halfOpenCalls >= $this->halfOpenMaxCalls) {
                return false;
            }

            $this->halfOpenCalls++;
            return true;
        }

        return true;
    }

    #[\Override]
    public function recordSuccess(): void
    {
        $this->failureCount = 0;
        $this->halfOpenCalls = 0;
        $this->openedAt = null;
        $this->state = self::STATE_CLOSED;
    }

    #[\Override]
    public function recordFailure(): void
    {
        $this->failureCount++;

        // 半开状态失败或失败次数达到阈值时进入断开状态
        if ($this->state === self::STATE_HALF_OPEN || $this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
            $this->openedAt = microtime(true);
            $this->halfOpenCalls = 0;
        }
    }

    #[\Override]
    public function state(): string
    {
        return $this->state;
    }

    #[\Override]
    public function metrics(): array
    {
        return [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'opened_at' => $this->openedAt,
            'recovery_timeout' => $this->recoveryTimeout,
            'half_open_max_calls' => $this->halfOpenMaxCalls,
        ];
    }

    /**
     * 执行任务，断路器打开时调用 fallback
     *
     * @param callable $task 任务回调
     * @param callable|null $fallback 兜底回调
     * @return mixed
     * @throws \RuntimeException 断路器打开且无 fallback 时抛出
     */
    public function execute(callable $task, ?callable $fallback = null): mixed
    {
        if (!$this->allowRequest()) {
            if ($fallback !== null) {
                return $fallback();
            }

            throw new \RuntimeException('Circuit breaker is open');
        }

        try {
            $result = $task();
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * 强制打开断路器
     *
     * @return void
     */
    public function forceOpen(): void
    {
        $this->state = self::STATE_OPEN;
        $this->openedAt = microtime(true);
        $this->halfOpenCalls = 0;
    }

    /**
     * 强制关闭断路器
     *
     * @return void
     */
    public function forceClose(): void
    {
        $this->recordSuccess();
    }
}
