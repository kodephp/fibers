<?php

declare(strict_types=1);

namespace Kode\Fibers\Contracts;

interface LoadBalancerInterface
{
    public function nextNode(array $nodes): string|int|null;

    public function distribute(array $items, int $workers): array;
}
