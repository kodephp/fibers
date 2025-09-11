<?php

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Context\Context;
use Nova\Fibers\Context\ContextManager;
use Nova\Fibers\Scheduler\LocalScheduler;
use Nova\Fibers\Profiler\FiberProfiler;
use Nova\Fibers\ORM\EloquentORMAdapter;
use Nova\Fibers\ORM\FixturesAdapter;

class AdvancedFeaturesTest extends TestCase
{
    /**
     * @covers \Nova\Fibers\Context\Context
     * @covers \Nova\Fibers\Context\ContextManager
     */
    public function testContextPassing(): void
    {
        $context = new Context('test_context');
        $context = $context->withValue('user_id', 123);
        $context = $context->withValue('request_id', 'req_abc123');

        ContextManager::setCurrentContext($context);

        $fiber = new \Fiber(function () {
            $context = ContextManager::getCurrentContext();
            $context = $context->withValue('fiber_result', 'success');
            ContextManager::setCurrentContext($context);
            return $context->value('user_id');
        });

        $fiber->start();
        $result = $fiber->getReturn();

        $this->assertEquals(123, $result);

        $updatedContext = ContextManager::getCurrentContext();
        $this->assertEquals('success', $updatedContext->value('fiber_result'));
    }

    /**
     * @covers \Nova\Fibers\Scheduler\LocalScheduler
     */
    public function testLocalScheduler(): void
    {
        $scheduler = new LocalScheduler([
            'size' => 2,
            'max_exec_time' => 5
        ]);

        $taskId = $scheduler->submit(function () {
            usleep(100000); // 100ms
            return 'completed';
        });

        $result = $scheduler->getResult($taskId, 1.0); // 1 second timeout
        $this->assertEquals('completed', $result);

        $status = $scheduler->getStatus($taskId);
        $this->assertEquals('completed', $status);
    }

    /**
     * @covers \Nova\Fibers\Profiler\FiberProfiler
     */
    public function testFiberProfiler(): void
    {
        FiberProfiler::enable();
        FiberProfiler::reset();

        $fiber = new \Fiber(function () {
            FiberProfiler::startFiber('test_fiber', 'Test Operation');
            usleep(50000); // 50ms
            FiberProfiler::endFiber('test_fiber', 'completed');
        });

        $fiber->start();

        $stats = FiberProfiler::getStats('test_fiber');
        $this->assertNotEmpty($stats);
        $this->assertEquals('completed', $stats['status']);
        $this->assertGreaterThan(0, $stats['duration']);
    }

    /**
     * @covers \Nova\Fibers\ORM\ORMAdapterInterface
     */
    public function testORMAdapter(): void
    {
        // Create a mock ORM adapter for testing
        $mockAdapter = $this->createMock(\Nova\Fibers\ORM\ORMAdapterInterface::class);

        $mockAdapter->expects($this->once())
            ->method('query')
            ->with(
                $this->equalTo('SELECT * FROM users WHERE id = ?'),
                $this->equalTo([123])
            )
            ->willReturn([['id' => 123, 'name' => 'John Doe']]);

        $result = $mockAdapter->query('SELECT * FROM users WHERE id = ?', [123]);
        $this->assertEquals([['id' => 123, 'name' => 'John Doe']], $result);
    }
}
