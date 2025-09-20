<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests\Core;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Core\EventLoop;
use Nova\Fibers\Support\Environment;

/**
 * EventLoop 扩展测试类
 *
 * @package Nova\Fibers\Tests\Core
 */
class EventLoopExtendedTest extends TestCase
{
    /**
     * 测试EventLoop单例模式
     *
     * @covers \Nova\Fibers\Core\EventLoop::getInstance
     * @return void
     */
    public function testSingleton(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $instance1 = EventLoop::getInstance();
        $instance2 = EventLoop::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }

    /**
     * 测试多个defer任务的执行顺序
     *
     * @covers \Nova\Fibers\Core\EventLoop::defer
     * @return void
     */
    public function testMultipleDefer(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $result = [];
        
        EventLoop::defer(function() use (&$result) {
            $result[] = 'first';
        });
        
        EventLoop::defer(function() use (&$result) {
            $result[] = 'second';
        });
        
        EventLoop::defer(function() use (&$result) {
            $result[] = 'third';
        });
        
        // 运行一次tick处理defer队列
        $eventLoop = EventLoop::getInstance();
        $reflection = new \ReflectionClass($eventLoop);
        $method = $reflection->getMethod('processDeferQueue');
        $method->setAccessible(true);
        $method->invoke($eventLoop);
        
        $this->assertEquals(['first', 'second', 'third'], $result);
    }

    /**
     * 测试多个delay任务
     *
     * @covers \Nova\Fibers\Core\EventLoop::delay
     * @return void
     */
    public function testMultipleDelay(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $result = [];
        
        EventLoop::delay(0.01, function() use (&$result) {
            $result[] = 'first';
        });
        
        EventLoop::delay(0.02, function() use (&$result) {
            $result[] = 'second';
        });
        
        // 等待足够时间让定时器到期
        usleep(25000); // 25ms
        
        // 运行一次tick处理定时器队列
        $eventLoop = EventLoop::getInstance();
        $reflection = new \ReflectionClass($eventLoop);
        $method = $reflection->getMethod('processTimers');
        $method->setAccessible(true);
        $method->invoke($eventLoop);
        
        // 检查结果顺序（定时器按到期时间执行）
        $this->assertEquals(['first', 'second'], $result);
    }

    /**
     * 测试多个repeat任务
     *
     * @covers \Nova\Fibers\Core\EventLoop::repeat
     * @return void
     */
    public function testMultipleRepeat(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $result1 = [];
        $result2 = [];
        
        $timerId1 = EventLoop::repeat(0.01, function() use (&$result1) {
            $result1[] = 'task1';
            if (count($result1) >= 2) {
                // 取消定时器
                EventLoop::cancel('non-existent-id'); // 测试取消不存在的ID
            }
        });
        
        $timerId2 = EventLoop::repeat(0.02, function() use (&$result2) {
            $result2[] = 'task2';
            if (count($result2) >= 1) {
                // 取消定时器
                EventLoop::cancel('non-existent-id'); // 测试取消不存在的ID
            }
        });
        
        // 手动触发重复定时器处理
        $eventLoop = EventLoop::getInstance();
        $reflection = new \ReflectionClass($eventLoop);
        $method = $reflection->getMethod('processRepeatTimers');
        $method->setAccessible(true);
        
        // 触发多次
        for ($i = 0; $i < 3; $i++) {
            $method->invoke($eventLoop);
            usleep(5000); // 5ms
        }
        
        // 验证结果
        $this->assertGreaterThanOrEqual(2, count($result1));
        $this->assertGreaterThanOrEqual(1, count($result2));
    }

    /**
     * 测试取消不存在的定时器
     *
     * @covers \Nova\Fibers\Core\EventLoop::cancel
     * @return void
     */
    public function testCancelNonExistentTimer(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        // 应该不会抛出异常
        EventLoop::cancel('non-existent-timer-id');
        $this->assertTrue(true); // 如果没有异常则测试通过
    }

    /**
     * 测试取消不存在的流
     *
     * @covers \Nova\Fibers\Core\EventLoop::cancelStream
     * @return void
     */
    public function testCancelNonExistentStream(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        // 创建一个临时文件用于测试
        $tempFile = tempnam(sys_get_temp_dir(), 'eventloop_test');
        $stream = fopen($tempFile, 'w');
        
        // 正常取消
        EventLoop::cancelStream($stream);
        
        // 取消已经关闭的流
        fclose($stream);
        EventLoop::cancelStream($stream);
        
        // 取消非资源类型
        EventLoop::cancelStream("not a resource");
        
        unlink($tempFile);
        $this->assertTrue(true); // 如果没有异常则测试通过
    }

    /**
     * 测试取消不存在的信号
     *
     * @covers \Nova\Fibers\Core\EventLoop::cancelSignal
     * @return void
     */
    public function testCancelNonExistentSignal(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        if (!function_exists('pcntl_signal')) {
            $this->markTestSkipped('pcntl extension is not available.');
        }

        // 应该不会抛出异常
        EventLoop::cancelSignal(999); // 不太可能使用的信号编号
        $this->assertTrue(true); // 如果没有异常则测试通过
    }

    /**
     * 测试无效流资源
     *
     * @covers \Nova\Fibers\Core\EventLoop::onReadable
     * @covers \Nova\Fibers\Core\EventLoop::onWritable
     * @return void
     */
    public function testInvalidStream(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        // 测试无效的可读流
        try {
            EventLoop::onReadable("not a resource", function() {});
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('Invalid stream resource', $e->getMessage());
        }

        // 测试无效的可写流
        try {
            EventLoop::onWritable("not a resource", function() {});
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('Invalid stream resource', $e->getMessage());
        }
    }
}