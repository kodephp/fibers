<?php

declare(strict_types=1);

namespace Kode\Fibers\Core;

use Kode\Fibers\Contracts\LoadBalancerInterface;

/**
 * 轮询负载均衡器
 *
 * 提供节点轮询与任务分桶能力。
 */
class RoundRobinBalancer implements LoadBalancerInterface
{
    protected int $cursor = 0;

    public function nextNode(array $nodes): string|int|null
    {
        if ($nodes === []) {
            return null;
        }

        $keys = array_keys($nodes);
        $index = $this->cursor % count($keys);
        $this->cursor++;

        return $keys[$index];
    }

    public function distribute(array $items, int $workers): array
    {
        $workers = max(1, $workers);
        $buckets = array_fill(0, $workers, []);
        $cursor = 0;

        foreach ($items as $key => $item) {
            $target = $cursor % $workers;
            $buckets[$target][$key] = $item;
            $cursor++;
        }

        return $buckets;
    }
}
