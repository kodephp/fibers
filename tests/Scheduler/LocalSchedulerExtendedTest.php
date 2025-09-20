<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests\Scheduler;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Scheduler\LocalScheduler;
use Nova\Fibers\Support\Environment;

/**
 * LocalScheduler 扩展测试类
 *
 * @package Nova\Fibers\Tests\Scheduler
 */
class LocalSchedulerExtendedTest extends TestCase
{
    /**
     * 测试带配置的LocalScheduler构造函数
     *
     * @covers \Nova\Fibers\Scheduler\LocalScheduler::__construct
     * @return void
     */
    public function testConstructWithConfig(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new LocalScheduler([
            'size' => 4,
            'timeout' => 10
        ]);
        
        $this->assertInstanceOf(LocalScheduler::class, $scheduler);
    }

    /**
     * 测试提交多个任务
     *
     * @covers \Nova\Fibers\Scheduler\LocalScheduler::submit
     * @return void
     */
    public function testSubmitMultipleTasks(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new LocalScheduler();
        
        $taskId1 = $scheduler->submit(function() {
            return 'result1';
        });
        
        $taskId2 = $scheduler->submit(function() {
            return 'result2';
        });
        
        $this->assertIsString($taskId1);
        $this->assertIsString($taskId2);
        $this->assertNotEquals($taskId1, $taskId2);
    }

    /**
     * 测试获取任务结果
     *
     * @covers \Nova\Fibers\Scheduler\LocalScheduler::getResult
     * @return void
     */
    public function testGetResult(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new LocalScheduler();
        
        $taskId = $scheduler->submit(function() {
            return 'test result';
        });
        
        $result = $scheduler->getResult($taskId);
        $this->assertEquals('test result', $result);
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
        
        $taskId = $scheduler->submit(function() {
            return 'test result';
        });
        
        // 任务可能已完成或正在运行
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
    }

    /**
     * 测试获取不存在的任务状态
     *
     * @covers \Nova\Fibers\Scheduler\LocalScheduler::getStatus
     * @return void
     */
    public function testGetStatusOfNonExistentTask(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new LocalScheduler();
        
        $status = $scheduler->getStatus('non-existent-task');
        $this->assertEquals('unknown', $status);
    }

    /**
     * 测试取消不存在的任务
     *
     * @covers \Nova\Fibers\Scheduler\LocalScheduler::cancel
     * @return void
     */
    public function testCancelNonExistentTask(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $scheduler = new LocalScheduler();
        
        $result = $scheduler->cancel('non-existent-task');
        $this->assertFalse($result);
    }
}