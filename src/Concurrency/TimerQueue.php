<?php

declare(strict_types=1);

namespace Kode\Fibers\Concurrency;

/**
 * 定时器最小堆（按到期时刻分桶）
 *
 * 堆的元素不是单个定时器，而是「同一到期时刻的定时器桶」{@see TimerBucket}。
 * 这样做同时拿到两个好处：
 *
 * - **稳定性**：同一时刻注册的定时器严格按注册顺序触发，桶内就是 FIFO 队列。
 *   若把 sequence 塞进堆的比较键来实现稳定排序，相同时刻的元素每次出堆都会
 *   被迫走满 O(log n) 的下沉路径，代价高昂。
 * - **性能**：批量注册相同延迟的定时器（delay(0)、统一超时时长等）在真实业务
 *   里极常见，此时堆中只有一个节点，进出都是 O(1)，整体从 O(n log n) 降到 O(n)。
 *
 * 时间点各不相同时，行为退化为标准二叉最小堆，复杂度 O(log m)（m 为不同时刻数）。
 * 取消采用惰性删除：出队时跳过，无效条目占比过高时整体重建。
 */
final class TimerQueue
{
    /**
     * @var array<int, TimerBucket> 一维数组表示的二叉最小堆（按 at 排序）
     */
    private array $heap = [];

    /**
     * 堆内桶数量
     */
    private int $size = 0;

    /**
     * 到期时刻 => 桶。键为 float 的二进制表示，避免浮点转字符串的精度损失
     *
     * @var array<string, TimerBucket>
     */
    private array $index = [];

    /**
     * 队列中定时器总数（含已取消但尚未清理的）
     *
     * 公开为字段而非只留 {@see self::count()}：事件循环每处理完一批就绪任务都要
     * 判一次「是否还有定时器」，在每秒数百万次调度的热路径上，一次属性读取与
     * 一次方法调用的差距是可观测的。
     *
     * @internal 外部只读，写入仅限本类
     */
    public int $total = 0;

    /**
     * 已取消但仍留在队列中的条目数（用于触发重建）
     */
    private int $staleCount = 0;

    /**
     * 插入定时器
     */
    public function insert(Timer $timer): void
    {
        $key = pack('d', $timer->at);
        $this->total++;

        $bucket = $this->index[$key] ?? null;

        if ($bucket !== null) {
            // 已有相同到期时刻的桶，直接追加，无需触碰堆
            $bucket->push($timer);

            return;
        }

        $bucket = new TimerBucket($timer->at);
        $bucket->push($timer);
        $this->index[$key] = $bucket;

        // 新时间点入堆：空位上浮，每层只写一次
        $index = $this->size++;
        $at = $bucket->at;

        while ($index > 0) {
            $parent = ($index - 1) >> 1;
            $parentBucket = $this->heap[$parent];

            if ($parentBucket->at <= $at) {
                break;
            }

            $this->heap[$index] = $parentBucket;
            $index = $parent;
        }

        $this->heap[$index] = $bucket;
    }

    /**
     * 查看最早到期且未取消的定时器（不弹出）
     */
    public function peek(): ?Timer
    {
        while ($this->size > 0) {
            $bucket = $this->heap[0];

            // 跳过桶头部已取消的条目
            while ($bucket->head < $bucket->tail) {
                $timer = $bucket->timers[$bucket->head];

                if (!$timer->cancelled) {
                    return $timer;
                }

                unset($bucket->timers[$bucket->head]);
                $bucket->head++;
                $this->total--;

                if ($this->staleCount > 0) {
                    $this->staleCount--;
                }
            }

            // 桶已排空，移出堆
            $this->removeRootBucket();
        }

        return null;
    }

    /**
     * 弹出一个「已到期且未取消」的定时器，没有则返回 null
     *
     * 相比先 peek() 判断再 extract() 取出，这里把查找与摘除合并为一次遍历，
     * 避免事件循环每触发一个定时器就重复走两遍堆顶清理逻辑。
     *
     * @param float $now           当前单调时钟时间
     * @param int   $sequenceLimit 只处理序号不大于该值的定时器，用于隔离本轮
     *                             回调中新注册的零延迟定时器，防止事件循环饥饿
     */
    public function extractExpired(float $now, int $sequenceLimit): ?Timer
    {
        while ($this->size > 0) {
            $bucket = $this->heap[0];

            if ($bucket->at > $now) {
                return null;
            }

            while ($bucket->head < $bucket->tail) {
                $timer = $bucket->timers[$bucket->head];

                if ($timer->cancelled) {
                    unset($bucket->timers[$bucket->head]);
                    $bucket->head++;
                    $this->total--;

                    if ($this->staleCount > 0) {
                        $this->staleCount--;
                    }

                    continue;
                }

                // 桶内序号单调递增，遇到超出本轮范围的即可停手
                if ($timer->sequence > $sequenceLimit) {
                    return null;
                }

                unset($bucket->timers[$bucket->head]);
                $bucket->head++;
                $this->total--;

                if ($bucket->isDrained()) {
                    $this->removeRootBucket();
                }

                return $timer;
            }

            $this->removeRootBucket();
        }

        return null;
    }

    /**
     * 弹出最早到期且未取消的定时器
     */
    public function extract(): ?Timer
    {
        $timer = $this->peek();

        if ($timer === null) {
            return null;
        }

        $bucket = $this->heap[0];
        unset($bucket->timers[$bucket->head]);
        $bucket->head++;
        $this->total--;

        if ($bucket->isDrained()) {
            $this->removeRootBucket();
        }

        return $timer;
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

    /**
     * 队列中定时器总数
     */
    public function count(): int
    {
        return $this->total;
    }

    /**
     * 清空所有定时器
     */
    public function clear(): void
    {
        $this->heap = [];
        $this->index = [];
        $this->size = 0;
        $this->total = 0;
        $this->staleCount = 0;
    }

    /**
     * 移除堆顶的桶并重建堆序
     */
    private function removeRootBucket(): void
    {
        $root = $this->heap[0];
        unset($this->index[pack('d', $root->at)]);

        $last = $this->heap[--$this->size];
        unset($this->heap[$this->size]);

        if ($this->size === 0) {
            return;
        }

        // 空位下沉：先让空位移到合适深度，最后把末位元素落位
        $index = 0;
        $at = $last->at;
        $half = $this->size >> 1;

        while ($index < $half) {
            $child = 2 * $index + 1;
            $right = $child + 1;
            $childBucket = $this->heap[$child];

            if ($right < $this->size) {
                $rightBucket = $this->heap[$right];

                if ($rightBucket->at < $childBucket->at) {
                    $child = $right;
                    $childBucket = $rightBucket;
                }
            }

            if ($at <= $childBucket->at) {
                break;
            }

            $this->heap[$index] = $childBucket;
            $index = $child;
        }

        $this->heap[$index] = $last;
    }

    /**
     * 无效条目超过半数时清理，避免内存无限膨胀
     */
    private function compactIfNeeded(): void
    {
        if ($this->total < 32 || $this->staleCount * 2 < $this->total) {
            return;
        }

        $survivors = [];

        for ($i = 0; $i < $this->size; $i++) {
            $bucket = $this->heap[$i];

            for ($j = $bucket->head; $j < $bucket->tail; $j++) {
                $timer = $bucket->timers[$j];

                if (!$timer->cancelled) {
                    $survivors[] = $timer;
                }
            }
        }

        $this->clear();

        foreach ($survivors as $timer) {
            $this->insert($timer);
        }
    }
}
