<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Support\Environment;

/**
 * FiberPool 测试类
 *
 * @package Nova\Fibers\Tests
 */
class FiberPoolTest extends TestCase
{
    /**
     * 测试环境是否支持纤程
     *
     * @covers \Nova\Fibers\Support\Environment::checkFiberSupport
     * @return void
     */
    public function testEnvironmentSupportsFibers(): void
    {
        $this->assertTrue(
            Environment::checkFiberSupport(),
            'Environment should support fibers'
        );
    }

    /**
     * 测试创建纤程池
     *
     * @covers \Nova\Fibers\Core\FiberPool::__construct
     * @return void
     */
    public function testCreateFiberPool(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $pool = new FiberPool(['size' => 4]);

        $this->assertInstanceOf(FiberPool::class, $pool);
    }

    /**
     * 测试并行执行任务
     *
     * @covers \Nova\Fibers\Core\FiberPool::concurrent
     * @return void
     */
    public function testConcurrentExecution(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $pool = new FiberPool(['size' => 4]);

        $tasks = [
            fn() => 1 + 1,
            fn() => 2 + 2,
            fn() => 3 + 3,
            fn() => 4 + 4,
        ];

        $results = $pool->concurrent($tasks);

        $this->assertCount(4, $results);
        $this->assertEquals([2, 4, 6, 8], $results);
    }

    /**
     * 测试带超时的任务执行
     *
     * @covers \Nova\Fibers\Core\FiberPool::concurrent
     * @return void
     */
    public function testRunWithTimeout(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task execution timed out after 0.001 seconds');

        $pool = new FiberPool(['size' => 2]);

        $tasks = [
            fn() => usleep(10000) ?: 'done', // 10ms
        ];

        // 设置超时为1毫秒，任务需要10毫秒，应该抛出异常
        $pool->concurrent($tasks, 0.001);
    }
}
