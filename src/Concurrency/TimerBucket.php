<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

/**
 * 同一到期时刻的定时器 FIFO 桶
 *
 * 现实中大量定时器会共享同一个到期时间点（批量 delay(0)、一批任务用同样的
 * 超时时长等）。把它们收进一个桶后，最小堆只需要为「不同的时间点」排序，
 * 桶内则是纯粹的 O(1) 队列进出，同时天然保持注册顺序。
 *
 * @internal
 */
final class TimerBucket
{
    /**
     * @var array<int, Timer> 以 head/tail 游标模拟的环形队列
     */
    public array $timers = [];

    /**
     * 出队游标
     */
    public int $head = 0;

    /**
     * 入队游标
     */
    public int $tail = 0;

    public function __construct(
        public readonly float $at,
    ) {
    }

    /**
     * 追加一个定时器
     */
    public function push(Timer $timer): void
    {
        $this->timers[$this->tail++] = $timer;
    }

    /**
     * 桶内是否已无待触发定时器
     */
    public function isDrained(): bool
    {
        return $this->head >= $this->tail;
    }

    /**
     * 桶内剩余定时器数量（含已取消但未清理的）
     */
    public function remaining(): int
    {
        return $this->tail - $this->head;
    }
}
