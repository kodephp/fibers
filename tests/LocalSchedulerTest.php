<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Scheduler\LocalScheduler;
use Nova\Fibers\Support\Environment;

/**
 * LocalScheduler 测试类
 *
 * @package Nova\Fibers\Tests
 */
class LocalSchedulerTest extends TestCase
{
    /**
     * 测试创建本地调度器
     *
     * @covers \Nova\Fibers\Scheduler\LocalScheduler::__construct
     * @return void
     */
    public function testCreateLocalScheduler(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new LocalScheduler();

        $this->assertInstanceOf(LocalScheduler::class, $scheduler);
    }

    /**
     * 测试提交任务
     *
     * @covers \Nova\Fibers\Scheduler\LocalScheduler::submit
     * @return void
     */
    public function testSubmit(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new LocalScheduler();
        
        $taskId = $scheduler->submit(function() {
            return 'test result';
        });
        
        $this->assertIsString($taskId);
        $this->assertStringStartsWith('task_', $taskId);
    }

    /**
     * 测试获取任务状态
     *
     * @covers \Nova\Fibers\Scheduler\LocalScheduler::getStatus
     * @return void
     */
    public function testGetStatus(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new LocalScheduler();
        
        // 测试不存在的任务
        $status = $scheduler->getStatus('non-existent-task');
        $this->assertEquals('unknown', $status);
        
        // 测试存在的任务
        $taskId = $scheduler->submit(function() {
            return 'test result';
        });
        
        $status = $scheduler->getStatus($taskId);
        $this->assertContains($status, ['pending', 'running', 'completed', 'failed', 'cancelled', 'unknown']);
    }

    /**
     * 测试取消任务
     *
     * @covers \Nova\Fibers\Scheduler\LocalScheduler::cancel
     * @return void
     */
    public function testCancel(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new LocalScheduler();
        
        // 测试取消不存在的任务
        $result = $scheduler->cancel('non-existent-task');
        $this->assertFalse($result);
        
        // 测试取消存在的任务
        $taskId = $scheduler->submit(function() {
            return 'test result';
        });
        
        $result = $scheduler->cancel($taskId);
        $this->assertTrue($result);
    }

    /**
     * 测试获取集群信息
     *
     * @covers \Nova\Fibers\Scheduler\LocalScheduler::getClusterInfo
     * @return void
     */
    public function testGetClusterInfo(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new LocalScheduler();
        
        $clusterInfo = $scheduler->getClusterInfo();
        
        $this->assertIsArray($clusterInfo);
        $this->assertArrayHasKey('nodes', $clusterInfo);
        $this->assertArrayHasKey('total_tasks', $clusterInfo);
        $this->assertIsArray($clusterInfo['nodes']);
        $this->assertIsInt($clusterInfo['total_tasks']);
    }
}