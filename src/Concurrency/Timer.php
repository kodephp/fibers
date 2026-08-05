<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Closure;

/**
 * 定时器句柄
 *
 * 由 {@see Scheduler::delay()} / {@see Scheduler::repeat()} 返回，
 * 可用于在到期前取消定时任务。
 *
 * 提供 {@see self::id}、{@see self::cancel} 等稳定公共接口，供调用方持有并取消定时任务。
 *
 * 内部实现位于调度器最热的路径上，已做零额外分配优化：不再为每个定时器
 * 构造取消回调闭包（改为直接引用所属堆 {@see TimerQueue::markStale()}）。
 */
final class Timer
{
    /**
     * 是否已取消
     *
     * @internal 公开仅为让 {@see TimerQueue} 以属性读取替代方法调用；
     *           外部请使用 {@see self::isCancelled()}。
     */
    public bool $cancelled = false;

    /**
     * 下一次触发的时间点（秒，单调时钟）
     *
     * @internal 同上，外部请使用 {@see self::at()}。
     */
    public float $at;

    /**
     * 惰性求值的 ID 缓存
     */
    private ?string $identifier = null;

    /**
     * @param float            $at       触发时间点（单调时钟秒）
     * @param int              $sequence 入堆序号，用于同一时间点的 FIFO 稳定排序
     * @param Closure          $callback 到期回调（公开以便调度器直接调用，省去一层方法转发）
     * @param float|null       $interval 周期间隔（秒），null 表示一次性定时器
     * @param Closure|null     $onCancel 取消时的通知回调（保留以兼容历史签名；内部已改用 $queue）
     * @param TimerQueue|null  $queue    所属定时器堆，取消时通知其做惰性回收统计
     */
    public function __construct(
        float $at,
        public readonly int $sequence,
        public readonly Closure $callback,
        public readonly ?float $interval = null,
        private readonly ?Closure $onCancel = null,
        private readonly ?TimerQueue $queue = null,
    ) {
        $this->at = $at;
    }

    /**
     * 定时器唯一 ID（形如 timer#123）
     *
     * ID 只用于调试与日志，却要为每个定时器做一次字符串拼接与分配。
     * 批量注册定时器是极常见的用法，因此推迟到首次访问时才生成。
     */
    public function id(): string
    {
        return $this->identifier ??= 'timer#' . $this->sequence;
    }

    /**
     * 兼容以属性方式读取 $timer->id
     */
    public function __get(string $property): mixed
    {
        if ($property === 'id') {
            return $this->id();
        }

        trigger_error(
            sprintf('未定义的属性 %s::$%s', self::class, $property),
            E_USER_WARNING
        );

        return null;
    }

    /**
     * 获取触发时间点（单调时钟秒）
     */
    public function at(): float
    {
        return $this->at;
    }

    /**
     * 是否为周期性定时器
     */
    public function isPeriodic(): bool
    {
        return $this->interval !== null;
    }

    /**
     * 取消定时器
     */
    public function cancel(): void
    {
        if ($this->cancelled) {
            return;
        }

        $this->cancelled = true;

        if ($this->onCancel !== null) {
            ($this->onCancel)();
        }

        $this->queue?->markStale();
    }

    /**
     * 是否已取消
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * 触发回调
     *
     * @internal 仅供 Scheduler 调用
     */
    public function fire(): void
    {
        if ($this->cancelled) {
            return;
        }

        ($this->callback)();
    }

    /**
     * 重排下一次触发时间（周期性定时器）
     *
     * @internal 仅供 Scheduler 调用
     */
    public function reschedule(float $now): void
    {
        if ($this->interval === null) {
            return;
        }

        // 以 now 为基准推进，避免长时间阻塞后产生大量补偿触发
        $this->at = $now + $this->interval;
    }
}
