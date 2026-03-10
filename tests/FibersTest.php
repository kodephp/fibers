<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests;

use Kode\Fibers\Context\Context;
use Kode\Fibers\Exceptions\FiberException;
use Kode\Fibers\Fibers;
use PHPUnit\Framework\TestCase;

class FibersTest extends TestCase
{
    public function testGoAlias(): void
    {
        $result = Fibers::go(fn() => 'ok');
        $this->assertSame('ok', $result);
    }

    public function testWithContext(): void
    {
        $result = Fibers::withContext(
            ['trace_id' => 'trace-001', 'tenant' => 'kode'],
            fn() => Context::get('trace_id') . ':' . Context::get('tenant')
        );

        $this->assertSame('trace-001:kode', $result);
    }

    public function testBatchConvenienceApi(): void
    {
        $items = ['a' => 2, 'b' => 3, 'c' => 4];
        $results = Fibers::batch(
            $items,
            fn(int $item, string $key) => $key . '-' . ($item * 2),
            2
        );

        $this->assertSame(['a' => 'a-4', 'b' => 'b-6', 'c' => 'c-8'], $results);
    }

    public function testBatchThrowsFiberException(): void
    {
        $this->expectException(FiberException::class);
        Fibers::batch(
            [1, 2, 3],
            function (int $item) {
                if ($item === 2) {
                    throw new \RuntimeException('boom');
                }

                return $item;
            },
            2
        );
    }

    public function testRuntimeFeatures(): void
    {
        $features = Fibers::runtimeFeatures();
        $this->assertArrayHasKey('php85_or_newer', $features);
        $this->assertArrayHasKey('safe_destruct_supported', $features);
        $this->assertArrayHasKey('native_fiber', $features);
    }

    public function testRoadmapList(): void
    {
        $roadmap = Fibers::roadmap();
        $keys = array_column($roadmap, 'key');

        $this->assertContains('php85_compatibility', $keys);
        $this->assertContains('distributed_scheduler', $keys);
        $this->assertContains('orm_adapter', $keys);
    }

    public function testResilientBatch(): void
    {
        $response = Fibers::resilientBatch(
            ['a' => 1, 'b' => 2, 'c' => 3],
            static function (int $item, string $key): string {
                if ($key === 'b') {
                    throw new \RuntimeException('b failed');
                }

                return $key . ':' . ($item * 10);
            },
            [
                'concurrency' => 2,
                'max_retries' => 0,
                'fail_fast' => false,
                'failure_threshold' => 2,
            ]
        );

        $this->assertSame('a:10', $response['results']['a']);
        $this->assertArrayHasKey('b', $response['errors']);
        $this->assertSame(3, $response['metrics']['total']);
    }

    public function testDistributedScheduleApi(): void
    {
        $response = Fibers::scheduleDistributed(
            ['t1' => ['name' => 'alpha'], 't2' => ['name' => 'beta'], 't3' => ['name' => 'gamma']],
            ['node-a' => ['healthy' => true], 'node-b' => ['healthy' => true]]
        );

        $this->assertArrayHasKey('assignments', $response);
        $this->assertArrayHasKey('unassigned', $response);
        $this->assertEmpty($response['unassigned']);
        $this->assertNotEmpty($response['assignments']);
    }

    public function testResilientRunWithFallback(): void
    {
        $result = Fibers::resilientRun(
            static function () {
                throw new \RuntimeException('always failed');
            },
            [
                'max_retries' => 0,
                'failure_threshold' => 1,
                'fallback' => static fn() => 'fallback-ok',
            ]
        );

        $this->assertSame('fallback-ok', $result);
    }

    public function testScheduleDistributedAdvancedWithTagAndHealth(): void
    {
        $tasks = [
            'task-1' => ['required_tags' => ['gpu']],
            'task-2' => ['required_tags' => ['cpu']],
            'task-3' => ['required_tags' => ['memory']],
        ];

        $nodes = [
            'node-a' => ['healthy' => true, 'tags' => ['gpu', 'cpu']],
            'node-b' => ['healthy' => true, 'tags' => ['cpu']],
        ];

        $response = Fibers::scheduleDistributedAdvanced(
            $tasks,
            $nodes,
            ['unhealthy_nodes' => ['node-b']]
        );

        $this->assertArrayHasKey('assignments', $response);
        $this->assertArrayHasKey('unassigned', $response);
        $this->assertArrayHasKey('node-a', $response['assignments']);
        $this->assertArrayHasKey('task-3', $response['unassigned']);
    }
}
