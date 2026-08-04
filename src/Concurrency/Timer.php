<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

use Closure;

/**
 * 定时器句柄
 *
 * 由 {@see Scheduler::delay()} / {@see Scheduler::repeat()} 返回，
 * 可用于在到期前取消定时任务。
 */
final class Timer
{
    /**
     * 是否已取消
     */
    private bool $cancelled = false;

    /**
     * 下一次触发的时间点（秒，单调时钟）
     */
    private float $at;

    /**
     * @param string       $id       定时器唯一 ID
     * @param float        $at       触发时间点（单调时钟秒）
     * @param int          $sequence 入堆序号，用于同一时间点的 FIFO 稳定排序
     * @param Closure      $callback 到期回调
     * @param float|null   $interval 周期间隔（秒），null 表示一次性定时器
     * @param Closure|null $onCancel 取消时的通知回调（供定时器堆做惰性回收统计）
     */
    public function __construct(
        public readonly string $id,
        float $at,
        public readonly int $sequence,
        private readonly Closure $callback,
        public readonly ?float $interval = null,
        private readonly ?Closure $onCancel = null,
    ) {
        $this->at = $at;
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
