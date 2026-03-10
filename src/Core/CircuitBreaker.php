<?php

declare(strict_types=1);

namespace Kode\Fibers\Core;

use Kode\Fibers\Contracts\CircuitBreakerInterface;

/**
 * 断路器实现
 *
 * 用于在连续失败时快速失败，避免雪崩。
 */
class CircuitBreaker implements CircuitBreakerInterface
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

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

    public function allowRequest(): bool
    {
        if ($this->state === self::STATE_CLOSED) {
            return true;
        }

        if ($this->state === self::STATE_OPEN) {
            $openedAt = $this->openedAt ?? microtime(true);
            if ((microtime(true) - $openedAt) >= $this->recoveryTimeout) {
                $this->state = self::STATE_HALF_OPEN;
                $this->halfOpenCalls = 0;
            } else {
                return false;
            }
        }

        if ($this->state === self::STATE_HALF_OPEN) {
            if ($this->halfOpenCalls >= $this->halfOpenMaxCalls) {
                return false;
            }

            $this->halfOpenCalls++;
            return true;
        }

        return true;
    }

    public function recordSuccess(): void
    {
        $this->failureCount = 0;
        $this->halfOpenCalls = 0;
        $this->openedAt = null;
        $this->state = self::STATE_CLOSED;
    }

    public function recordFailure(): void
    {
        $this->failureCount++;

        if ($this->state === self::STATE_HALF_OPEN || $this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
            $this->openedAt = microtime(true);
            $this->halfOpenCalls = 0;
        }
    }

    public function state(): string
    {
        return $this->state;
    }

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

    public function forceOpen(): void
    {
        $this->state = self::STATE_OPEN;
        $this->openedAt = microtime(true);
        $this->halfOpenCalls = 0;
    }

    public function forceClose(): void
    {
        $this->recordSuccess();
    }
}
