<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests\Core;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Core\Scheduler;
use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Support\Environment;

/**
 * Scheduler 扩展测试类
 *
 * @package Nova\Fibers\Tests\Core
 */
class SchedulerExtendedTest extends TestCase
{
    /**
     * 测试Scheduler构造函数
     *
     * @covers \Nova\Fibers\Core\Scheduler::__construct
     * @return void
     */
    public function testConstruct(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new Scheduler();
        
        $this->assertInstanceOf(Scheduler::class, $scheduler);
        $this->assertInstanceOf(\Nova\Fibers\Channel\Channel::class, $scheduler->getTaskQueue());
    }

    /**
     * 测试添加多个任务
     *
     * @covers \Nova\Fibers\Core\Scheduler::addTask
     * @return void
     */
    public function testAddMultipleTasks(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new Scheduler();
        
        $taskId1 = $scheduler->addTask(function() {
            return 'task1';
        });
        
        $taskId2 = $scheduler->addTask(function() {
            return 'task2';
        });
        
        $this->assertIsString($taskId1);
        $this->assertIsString($taskId2);
        $this->assertNotEquals($taskId1, $taskId2);
    }

    /**
     * 测试获取活跃Fiber数量
     *
     * @covers \Nova\Fibers\Core\Scheduler::getActiveFiberCount
     * @return void
     */
    public function testGetActiveFiberCount(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new Scheduler();
        
        $initialCount = $scheduler->getActiveFiberCount();
        $this->assertEquals(0, $initialCount);
    }

    /**
     * 测试Scheduler停止功能
     *
     * @covers \Nova\Fibers\Core\Scheduler::stop
     * @return void
     */
    public function testStop(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new Scheduler();
        $scheduler->stop();
        
        // 只需确保不抛出异常
        $this->assertTrue(true);
    }
}