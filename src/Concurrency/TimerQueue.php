<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

/**
 * 定时器最小堆
 *
 * 以「触发时间 + 入堆序号」为键的二叉最小堆，取最早到期定时器为 O(1)，
 * 插入 / 弹出为 O(log n)。取消采用惰性删除：被取消的定时器在出堆时跳过，
 * 当无效条目占比过高时整体重建，避免堆无限增长。
 */
final class TimerQueue
{
    /**
     * @var Timer[] 一维数组表示的二叉堆（下标从 0 开始）
     */
    private array $heap = [];

    /**
     * 已取消但仍留在堆中的条目数（用于触发重建）
     */
    private int $staleCount = 0;

    /**
     * 插入定时器
     */
    public function insert(Timer $timer): void
    {
        $this->heap[] = $timer;
        $this->siftUp(count($this->heap) - 1);
    }

    /**
     * 查看最早到期且未取消的定时器（不弹出）
     */
    public function peek(): ?Timer
    {
        $this->purgeTop();

        return $this->heap[0] ?? null;
    }

    /**
     * 弹出最早到期且未取消的定时器
     */
    public function extract(): ?Timer
    {
        $this->purgeTop();

        if ($this->heap === []) {
            return null;
        }

        return $this->removeRoot();
    }

    /**
     * 标记存在一个被取消的条目
     */
    public function markStale(): void
    {
        $this->staleCount++;
        $this->compactIfNeeded();
    }

    public function isEmpty(): bool
    {
        return $this->peek() === null;
    }

    public function count(): int
    {
        return count($this->heap);
    }

    /**
     * 清空所有定时器
     */
    public function clear(): void
    {
        $this->heap = [];
        $this->staleCount = 0;
    }

    /**
     * 丢弃堆顶所有已取消的定时器
     */
    private function purgeTop(): void
    {
        while ($this->heap !== [] && $this->heap[0]->isCancelled()) {
            $this->removeRoot();
            $this->staleCount = max(0, $this->staleCount - 1);
        }
    }

    private function removeRoot(): Timer
    {
        $root = $this->heap[0];
        $last = array_pop($this->heap);

        if ($this->heap !== []) {
            $this->heap[0] = $last;
            $this->siftDown(0);
        }

        return $root;
    }

    /**
     * 无效条目超过半数时重建堆，避免内存无限膨胀
     */
    private function compactIfNeeded(): void
    {
        $total = count($this->heap);

        if ($total < 32 || $this->staleCount * 2 < $total) {
            return;
        }

        $alive = array_values(array_filter(
            $this->heap,
            static fn(Timer $timer): bool => !$timer->isCancelled()
        ));

        $this->heap = [];
        $this->staleCount = 0;

        foreach ($alive as $timer) {
            $this->insert($timer);
        }
    }

    private function siftUp(int $index): void
    {
        while ($index > 0) {
            $parent = intdiv($index - 1, 2);

            if (!$this->less($index, $parent)) {
                return;
            }

            $this->swap($index, $parent);
            $index = $parent;
        }
    }

    private function siftDown(int $index): void
    {
        $size = count($this->heap);

        while (true) {
            $left = 2 * $index + 1;
            $right = $left + 1;
            $smallest = $index;

            if ($left < $size && $this->less($left, $smallest)) {
                $smallest = $left;
            }

            if ($right < $size && $this->less($right, $smallest)) {
                $smallest = $right;
            }

            if ($smallest === $index) {
                return;
            }

            $this->swap($index, $smallest);
            $index = $smallest;
        }
    }

    private function less(int $a, int $b): bool
    {
        $timerA = $this->heap[$a];
        $timerB = $this->heap[$b];

        if ($timerA->at() !== $timerB->at()) {
            return $timerA->at() < $timerB->at();
        }

        // 同一时间点按入堆顺序触发，保证 FIFO 稳定性
        return $timerA->sequence < $timerB->sequence;
    }

    private function swap(int $a, int $b): void
    {
        [$this->heap[$a], $this->heap[$b]] = [$this->heap[$b], $this->heap[$a]];
    }
}
