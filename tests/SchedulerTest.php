<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Core\Scheduler;
use Nova\Fibers\Support\Environment;

/**
 * Scheduler 测试类
 *
 * @package Nova\Fibers\Tests
 */
class SchedulerTest extends TestCase
{
    /**
     * 测试创建调度器
     *
     * @covers \Nova\Fibers\Core\Scheduler::__construct
     * @return void
     */
    public function testCreateScheduler(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new Scheduler();

        $this->assertInstanceOf(Scheduler::class, $scheduler);
    }

    /**
     * 测试添加任务到调度器
     *
     * @covers \Nova\Fibers\Core\Scheduler::addTask
     * @return void
     */
    public function testAddTask(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new Scheduler();
        
        $taskId = $scheduler->addTask(function() {
            return 'test task';
        });
        
        $this->assertIsString($taskId);
        $this->assertStringStartsWith('task_', $taskId);
    }

    /**
     * 测试获取任务队列
     *
     * @covers \Nova\Fibers\Core\Scheduler::getTaskQueue
     * @return void
     */
    public function testGetTaskQueue(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new Scheduler();
        
        $taskQueue = $scheduler->getTaskQueue();
        
        $this->assertInstanceOf(\Nova\Fibers\Channel\Channel::class, $taskQueue);
    }

    /**
     * 测试获取活跃纤程数量
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
        
        $this->assertIsInt($initialCount);
        $this->assertEquals(0, $initialCount);
    }
}