<?php

declare(strict_types=1);

namespace Kode\Fibers\Core;

use Kode\Fibers\Contracts\CircuitBreakerInterface;

/**
 * 多维度断路器
 *
 * 支持：
 * - 按异常类型熔断
 * - 按服务维度熔断
 * - 半开状态智能探测
 */
class MultiDimensionalCircuitBreaker implements CircuitBreakerInterface
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    protected string $state = self::STATE_CLOSED;
    protected ?float $openedAt = null;
    protected int $halfOpenCalls = 0;
    
    protected int $totalSuccesses = 0;
    protected int $totalFailures = 0;
    protected float $lastStateChange = 0;
    
    protected array $exceptionBreakers = [];
    protected array $serviceBreakers = [];
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'failure_threshold' => 5,
            'success_threshold' => 2,
            'recovery_timeout' => 30.0,
            'half_open_max_calls' => 3,
            'excluded_exceptions' => [],
            'exception_weights' => [],
            'service_specific_threshold' => [],
        ], $config);
    }

    public function allowRequest(?string $service = null, ?string $exceptionClass = null): bool
    {
        if ($this->state === self::STATE_CLOSED) {
            if ($exceptionClass && $this->shouldTripOnException($exceptionClass)) {
                return false;
            }
            return true;
        }

        if ($this->state === self::STATE_OPEN) {
            $openedAt = $this->openedAt ?? microtime(true);
            if ((microtime(true) - $openedAt) >= $this->config['recovery_timeout']) {
                $this->transitionToHalfOpen();
            } else {
                return false;
            }
        }

        if ($this->state === self::STATE_HALF_OPEN) {
            if ($service !== null && $this->isServiceOpen($service)) {
                return false;
            }
            
            if ($exceptionClass !== null && $this->isExceptionOpen($exceptionClass)) {
                return false;
            }

            if ($this->halfOpenCalls >= $this->config['half_open_max_calls']) {
                return false;
            }

            $this->halfOpenCalls++;
            return true;
        }

        return true;
    }

    public function recordSuccess(?string $service = null, ?string $exceptionClass = null): void
    {
        $this->totalSuccesses++;
        $this->halfOpenCalls = 0;
        $this->lastStateChange = microtime(true);

        if ($service !== null) {
            $this->recordServiceSuccess($service);
        }

        if ($exceptionClass !== null) {
            $this->clearExceptionBreaker($exceptionClass);
        }

        if ($this->state === self::STATE_HALF_OPEN) {
            $successThreshold = $this->config['success_threshold'];
            if ($this->totalSuccesses >= $successThreshold) {
                $this->transitionToClosed();
            }
        }
    }

    public function recordFailure(
        ?string $service = null,
        ?string $exceptionClass = null,
        ?\Throwable $exception = null
    ): void {
        $this->totalFailures++;
        $this->lastStateChange = microtime(true);

        if ($service !== null) {
            $this->recordServiceFailure($service);
        }

        if ($exceptionClass !== null) {
            $this->recordExceptionFailure($exceptionClass, $exception);
        }

        if ($this->state === self::STATE_HALF_OPEN) {
            $this->transitionToOpen();
            return;
        }

        if ($this->state === self::STATE_CLOSED) {
            $failureThreshold = $this->calculateFailureThreshold($service, $exceptionClass);
            if ($this->totalFailures >= $failureThreshold) {
                $this->transitionToOpen();
            }
        }
    }

    public function state(): string
    {
        return $this->state;
    }

    public function reset(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->totalSuccesses = 0;
        $this->totalFailures = 0;
        $this->halfOpenCalls = 0;
        $this->openedAt = null;
        $this->exceptionBreakers = [];
        $this->serviceBreakers = [];
    }

    public function metrics(): array
    {
        return [
            'state' => $this->state,
            'total_successes' => $this->totalSuccesses,
            'total_failures' => $this->totalFailures,
            'failure_rate' => $this->calculateFailureRate(),
            'opened_at' => $this->openedAt,
            'recovery_timeout' => $this->config['recovery_timeout'],
            'half_open_calls' => $this->halfOpenCalls,
            'half_open_max_calls' => $this->config['half_open_max_calls'],
            'exception_breakers' => $this->exceptionBreakers,
            'service_breakers' => $this->serviceBreakers,
            'last_state_change' => $this->lastStateChange,
        ];
    }

    public function execute(callable $task, ?callable $fallback = null, ?string $service = null): mixed
    {
        $exceptionClass = null;
        
        try {
            $result = $task();
            $this->recordSuccess($service);
            return $result;
        } catch (\Throwable $e) {
            $exceptionClass = get_class($e);
            $this->recordFailure($service, $exceptionClass, $e);
            
            if ($fallback !== null) {
                return $fallback($e, $service);
            }
            
            throw $e;
        }
    }

    /**
     * 按服务维度检查断路器状态
     */
    public function isServiceOpen(string $service): bool
    {
        if (!isset($this->serviceBreakers[$service])) {
            return false;
        }

        $breaker = $this->serviceBreakers[$service];
        if ($breaker['state'] === self::STATE_OPEN) {
            $openedAt = $breaker['opened_at'] ?? microtime(true);
            if ((microtime(true) - $openedAt) >= $this->config['recovery_timeout']) {
                $this->serviceBreakers[$service]['state'] = self::STATE_HALF_OPEN;
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * 按异常类型检查断路器状态
     */
    public function isExceptionOpen(string $exceptionClass): bool
    {
        if (!isset($this->exceptionBreakers[$exceptionClass])) {
            return false;
        }

        $breaker = $this->exceptionBreakers[$exceptionClass];
        if ($breaker['state'] === self::STATE_OPEN) {
            $openedAt = $breaker['opened_at'] ?? microtime(true);
            if ((microtime(true) - $openedAt) >= ($breaker['timeout'] ?? $this->config['recovery_timeout'])) {
                $this->exceptionBreakers[$exceptionClass]['state'] = self::STATE_HALF_OPEN;
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * 检查是否应该因异常类型而熔断
     */
    protected function shouldTripOnException(string $exceptionClass): bool
    {
        if (in_array($exceptionClass, $this->config['excluded_exceptions'], true)) {
            return false;
        }

        if (!isset($this->exceptionBreakers[$exceptionClass])) {
            return false;
        }

        return $this->isExceptionOpen($exceptionClass);
    }

    /**
     * 记录服务失败
     */
    protected function recordServiceFailure(string $service): void
    {
        if (!isset($this->serviceBreakers[$service])) {
            $this->serviceBreakers[$service] = [
                'state' => self::STATE_CLOSED,
                'failures' => 0,
                'opened_at' => null,
            ];
        }

        $this->serviceBreakers[$service]['failures']++;
        $threshold = $this->config['service_specific_threshold'][$service]
            ?? $this->config['failure_threshold'];

        if ($this->serviceBreakers[$service]['failures'] >= $threshold) {
            $this->serviceBreakers[$service]['state'] = self::STATE_OPEN;
            $this->serviceBreakers[$service]['opened_at'] = microtime(true);
        }
    }

    /**
     * 记录服务成功
     */
    protected function recordServiceSuccess(string $service): void
    {
        if (isset($this->serviceBreakers[$service])) {
            $this->serviceBreakers[$service]['failures'] = 0;
            if ($this->serviceBreakers[$service]['state'] === self::STATE_HALF_OPEN) {
                $this->serviceBreakers[$service]['state'] = self::STATE_CLOSED;
            }
        }
    }

    /**
     * 记录异常失败
     */
    protected function recordExceptionFailure(string $exceptionClass, ?\Throwable $exception): void
    {
        if (!isset($this->exceptionBreakers[$exceptionClass])) {
            $this->exceptionBreakers[$exceptionClass] = [
                'state' => self::STATE_CLOSED,
                'failures' => 0,
                'opened_at' => null,
                'timeout' => $this->getExceptionTimeout($exceptionClass, $exception),
            ];
        }

        $weight = $this->config['exception_weights'][$exceptionClass] ?? 1;
        $this->exceptionBreakers[$exceptionClass]['failures'] += $weight;

        if ($this->exceptionBreakers[$exceptionClass]['failures'] >= $this->config['failure_threshold']) {
            $this->exceptionBreakers[$exceptionClass]['state'] = self::STATE_OPEN;
            $this->exceptionBreakers[$exceptionClass]['opened_at'] = microtime(true);
        }
    }

    /**
     * 清除异常断路器
     */
    protected function clearExceptionBreaker(string $exceptionClass): void
    {
        if (isset($this->exceptionBreakers[$exceptionClass])) {
            $this->exceptionBreakers[$exceptionClass]['failures'] = 0;
            if ($this->exceptionBreakers[$exceptionClass]['state'] === self::STATE_HALF_OPEN) {
                $this->exceptionBreakers[$exceptionClass]['state'] = self::STATE_CLOSED;
            }
        }
    }

    /**
     * 获取异常超时时间
     */
    protected function getExceptionTimeout(string $exceptionClass, ?\Throwable $exception): float
    {
        if ($exception instanceof \Kode\Fibers\Exceptions\FiberException) {
            return $this->config['recovery_timeout'] * 0.5;
        }
        
        return $this->config['recovery_timeout'];
    }

    /**
     * 计算失败阈值
     */
    protected function calculateFailureThreshold(?string $service, ?string $exceptionClass): int
    {
        if ($service !== null && isset($this->config['service_specific_threshold'][$service])) {
            return $this->config['service_specific_threshold'][$service];
        }

        if ($exceptionClass !== null && isset($this->config['exception_weights'][$exceptionClass])) {
            $weight = $this->config['exception_weights'][$exceptionClass];
            return (int) ceil($this->config['failure_threshold'] / max(1, $weight));
        }

        return $this->config['failure_threshold'];
    }

    /**
     * 计算失败率
     */
    protected function calculateFailureRate(): float
    {
        $total = $this->totalSuccesses + $this->totalFailures;
        if ($total === 0) {
            return 0.0;
        }
        return round($this->totalFailures / $total, 4);
    }

    /**
     * 转换到半开状态
     */
    protected function transitionToHalfOpen(): void
    {
        $this->state = self::STATE_HALF_OPEN;
        $this->halfOpenCalls = 0;
        $this->lastStateChange = microtime(true);
    }

    /**
     * 转换到关闭状态
     */
    protected function transitionToClosed(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->totalSuccesses = 0;
        $this->totalFailures = 0;
        $this->halfOpenCalls = 0;
        $this->lastStateChange = microtime(true);
    }

    /**
     * 转换到打开状态
     */
    protected function transitionToOpen(): void
    {
        $this->state = self::STATE_OPEN;
        $this->openedAt = microtime(true);
        $this->lastStateChange = microtime(true);
    }
}
