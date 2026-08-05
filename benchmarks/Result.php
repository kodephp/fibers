<?php

declare(strict_types=1);

namespace Kode\Fibers\Benchmarks;

/**
 * 单个实现在单个场景下的压测结果
 */
final class Result
{
    public function __construct(
        public readonly string $impl,
        public readonly int $ops,
        public readonly float $median,
        public readonly float $best = 0.0,
        public readonly int $memory = 0,
        public readonly bool $skipped = false,
        public readonly string $reason = '',
    ) {
    }

    public static function skipped(string $impl, string $reason): self
    {
        return new self($impl, 0, 0.0, 0.0, 0, true, $reason);
    }

    /**
     * 按中位耗时换算的每秒操作数
     */
    public function opsPerSecond(): float
    {
        return $this->median > 0 ? $this->ops / $this->median : 0.0;
    }
}
