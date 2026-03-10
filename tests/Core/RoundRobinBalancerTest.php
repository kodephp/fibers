<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests\Core;

use Kode\Fibers\Core\RoundRobinBalancer;
use PHPUnit\Framework\TestCase;

class RoundRobinBalancerTest extends TestCase
{
    public function testDistribute(): void
    {
        $balancer = new RoundRobinBalancer();
        $buckets = $balancer->distribute(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], 2);

        $this->assertSame(['a' => 1, 'c' => 3], $buckets[0]);
        $this->assertSame(['b' => 2, 'd' => 4], $buckets[1]);
    }

    public function testNextNode(): void
    {
        $balancer = new RoundRobinBalancer();
        $nodes = ['n1' => [], 'n2' => []];

        $this->assertSame('n1', $balancer->nextNode($nodes));
        $this->assertSame('n2', $balancer->nextNode($nodes));
        $this->assertSame('n1', $balancer->nextNode($nodes));
    }
}
