<?php

declare(strict_types=1);

namespace Kode\Fibers\Tests\Support;

use Kode\Fibers\Support\RuntimeBridge;
use PHPUnit\Framework\TestCase;

class RuntimeBridgeTest extends TestCase
{
    public function testDetect(): void
    {
        $detected = RuntimeBridge::detect();
        $this->assertArrayHasKey('native_fiber', $detected);
        $this->assertArrayHasKey('swoole', $detected);
    }

    public function testRun(): void
    {
        $result = RuntimeBridge::run(static fn() => 'bridge-ok', 'native');
        $this->assertSame('bridge-ok', $result);
    }
}
