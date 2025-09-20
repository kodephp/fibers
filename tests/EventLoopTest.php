<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Core\EventLoop;
use Nova\Fibers\Support\Environment;

/**
 * EventLoop 测试类
 *
 * @package Nova\Fibers\Tests
 */
class EventLoopTest extends TestCase
{
    /**
     * 测试defer功能
     *
     * @covers \Nova\Fibers\Core\EventLoop::defer
     * @return void
     */
    public function testDefer(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $result = [];
        
        // 添加一个defer任务
        EventLoop::defer(function() use (&$result) {
            $result[] = 'deferred';
        });
        
        $result[] = 'immediate';
        
        // 运行一次tick处理defer队列
        $eventLoop = EventLoop::getInstance();
        $reflection = new \ReflectionClass($eventLoop);
        $method = $reflection->getMethod('processDeferQueue');
        $method->setAccessible(true);
        $method->invoke($eventLoop);
        
        $this->assertEquals(['immediate', 'deferred'], $result);
    }

    /**
     * 测试delay功能
     *
     * @covers \Nova\Fibers\Core\EventLoop::delay
     * @covers \Nova\Fibers\Core\EventLoop::cancel
     * @return void
     */
    public function testDelay(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $result = [];
        
        // 添加一个延迟任务
        $timerId = EventLoop::delay(0.01, function() use (&$result) {
            $result[] = 'delayed';
        });
        
        $result[] = 'immediate';
        
        // 等待足够时间让定时器到期
        usleep(15000); // 15ms
        
        // 运行一次tick处理定时器队列
        $eventLoop = EventLoop::getInstance();
        $reflection = new \ReflectionClass($eventLoop);
        $method = $reflection->getMethod('processTimers');
        $method->setAccessible(true);
        $method->invoke($eventLoop);
        
        $this->assertEquals(['immediate', 'delayed'], $result);
        
        // 测试取消定时器
        $result = [];
        $timerId = EventLoop::delay(0.1, function() use (&$result) {
            $result[] = 'should not execute';
        });
        
        EventLoop::cancel($timerId);
        
        // 等待足够时间
        usleep(150000); // 150ms
        
        // 运行一次tick处理定时器队列
        $method->invoke($eventLoop);
        
        $this->assertEmpty($result);
    }

    /**
     * 测试repeat功能
     *
     * @covers \Nova\Fibers\Core\EventLoop::repeat
     * @covers \Nova\Fibers\Core\EventLoop::cancel
     * @return void
     */
    public function testRepeat(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $result = [];
        $count = 0;
        $timerId = null;
        
        // 添加一个重复任务
        $timerId = EventLoop::repeat(1.0, function() use (&$result, &$count, &$timerId) {
            $result[] = 'repeated';
            $count++;
            
            // 执行3次后取消
            if ($count >= 3 && $timerId !== null) {
                EventLoop::cancel($timerId);
            }
        });
        
        // 手动触发重复定时器处理，避免时间等待
        $eventLoop = EventLoop::getInstance();
        $reflection = new \ReflectionClass($eventLoop);
        $method = $reflection->getMethod('processRepeatTimers');
        $method->setAccessible(true);
        
        // 第一次触发
        $method->invoke($eventLoop);
        $this->assertCount(1, $result);
        
        // 第二次触发
        $method->invoke($eventLoop);
        $this->assertCount(2, $result);
        
        // 第三次触发
        $method->invoke($eventLoop);
        $this->assertCount(3, $result);
        
        // 第四次触发（应该不会添加新结果，因为已经取消）
        $method->invoke($eventLoop);
        $this->assertCount(3, $result);
        
        $this->assertEquals(['repeated', 'repeated', 'repeated'], $result);
    }

    /**
     * 测试流可读事件
     *
     * @covers \Nova\Fibers\Core\EventLoop::onReadable
     * @covers \Nova\Fibers\Core\EventLoop::cancelStream
     * @return void
     */
    public function testOnReadable(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        if (!function_exists('stream_socket_pair') || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('stream_socket_pair function is not available or not supported on this platform.');
        }

        // 创建一对连接的socket
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($sockets === false) {
            $this->markTestSkipped('Failed to create socket pair.');
        }
        
        $result = [];
        
        // 监听第一个socket的可读事件
        EventLoop::onReadable($sockets[0], function($stream) use (&$result) {
            $data = fread($stream, 1024);
            $result[] = $data;
        });
        
        // 向第二个socket写入数据
        fwrite($sockets[1], "test data");
        
        // 运行一次tick处理流事件
        $eventLoop = EventLoop::getInstance();
        $reflection = new \ReflectionClass($eventLoop);
        $method = $reflection->getMethod('processStreams');
        $method->setAccessible(true);
        $method->invoke($eventLoop);
        
        $this->assertEquals(['test data'], $result);
        
        // 清理资源
        fclose($sockets[0]);
        fclose($sockets[1]);
    }

    /**
     * 测试流可写事件
     *
     * @covers \Nova\Fibers\Core\EventLoop::onWritable
     * @covers \Nova\Fibers\Core\EventLoop::cancelStream
     * @return void
     */
    public function testOnWritable(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        // 创建一个临时文件用于测试
        $tempFile = tempnam(sys_get_temp_dir(), 'eventloop_test');
        $stream = fopen($tempFile, 'w');
        if ($stream === false) {
            $this->markTestSkipped('Failed to create temporary file.');
        }
        
        stream_set_blocking($stream, false);
        
        $result = [];
        
        // 监听stream的可写事件
        EventLoop::onWritable($stream, function($stream) use (&$result) {
            $result[] = 'writable';
        });
        
        // 等待一段时间让事件触发
        usleep(10000);
        
        // 运行一次tick处理流事件
        $eventLoop = EventLoop::getInstance();
        $reflection = new \ReflectionClass($eventLoop);
        $method = $reflection->getMethod('processStreams');
        $method->setAccessible(true);
        $method->invoke($eventLoop);
        
        $this->assertEquals(['writable'], $result);
        
        // 清理资源
        fclose($stream);
        unlink($tempFile);
    }

    /**
     * 测试信号处理（仅在支持pcntl的系统上运行）
     *
     * @covers \Nova\Fibers\Core\EventLoop::onSignal
     * @covers \Nova\Fibers\Core\EventLoop::cancelSignal
     * @return void
     */
    public function testOnSignal(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        if (!function_exists('pcntl_signal')) {
            $this->markTestSkipped('pcntl extension is not available.');
        }

        $result = [];
        
        // 监听SIGUSR1信号
        EventLoop::onSignal(SIGUSR1, function($signal) use (&$result) {
            $result[] = $signal;
        });
        
        // 发送信号
        posix_kill(posix_getpid(), SIGUSR1);
        
        // 处理信号
        $eventLoop = EventLoop::getInstance();
        $reflection = new \ReflectionClass($eventLoop);
        $method = $reflection->getMethod('processSignals');
        $method->setAccessible(true);
        $method->invoke($eventLoop);
        
        $this->assertEquals([SIGUSR1], $result);
        
        // 取消信号监听
        EventLoop::cancelSignal(SIGUSR1);
    }
}