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
}
