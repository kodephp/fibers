<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests\Core;

use Kode\Fibers\Core\DistributedScheduler;
use PHPUnit\Framework\TestCase;

class DistributedSchedulerTest extends TestCase
{
    public function testDispatchWithRequiredTags(): void
    {
        $scheduler = new DistributedScheduler();
        $scheduler->registerNode('node-a', ['healthy' => true, 'tags' => ['gpu', 'cpu']]);
        $scheduler->registerNode('node-b', ['healthy' => true, 'tags' => ['cpu']]);

        $response = $scheduler->dispatch([
            'job-1' => ['required_tags' => ['gpu']],
            'job-2' => ['required_tags' => ['cpu']],
            'job-3' => ['required_tags' => ['memory']],
        ]);

        $this->assertArrayHasKey('assignments', $response);
        $this->assertArrayHasKey('unassigned', $response);
        $this->assertArrayHasKey('job-3', $response['unassigned']);
    }

    public function testSetNodeHealth(): void
    {
        $scheduler = new DistributedScheduler();
        $scheduler->registerNode('node-a', ['healthy' => true]);
        $scheduler->setNodeHealth('node-a', false);

        $response = $scheduler->dispatch(['job-1' => ['foo' => 'bar']]);
        $this->assertArrayHasKey('job-1', $response['unassigned']);
    }
}
