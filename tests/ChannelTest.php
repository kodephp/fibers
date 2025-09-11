<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Channel\Channel;
use Nova\Fibers\Support\Environment;

/**
 * Channel 测试类
 *
 * @package Nova\Fibers\Tests
 */
class ChannelTest extends TestCase
{
    /**
     * 测试创建通道
     *
     * @covers \Nova\Fibers\Channel\Channel::__construct
     * @return void
     */
    public function testCreateChannel(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $channel = new Channel(10);

        $this->assertInstanceOf(Channel::class, $channel);
    }

    /**
     * 测试通道推送和弹出数据
     *
     * @covers \Nova\Fibers\Channel\Channel::push
     * @covers \Nova\Fibers\Channel\Channel::pop
     * @return void
     */
    public function testChannelPushAndPop(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $channel = new Channel(2);

        // 推送数据
        $channel->push('message1');
        $channel->push('message2');

        // 弹出数据
        $this->assertEquals('message1', $channel->pop());
        $this->assertEquals('message2', $channel->pop());
    }

    /**
     * 测试通道关闭
     *
     * @covers \Nova\Fibers\Channel\Channel::close
     * @covers \Nova\Fibers\Channel\Channel::isClosed
     * @return void
     */
    public function testChannelClose(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $channel = new Channel(1);
        $channel->push('message');
        $channel->close();

        $this->assertTrue($channel->isClosed());
        $this->assertEquals('message', $channel->pop());
        $this->assertNull($channel->pop());
    }

    /**
     * 测试静态make方法
     *
     * @covers \Nova\Fibers\Channel\Channel::make
     * @return void
     */
    public function testChannelMake(): void
    {
        if (!Environment::checkFiberSupport()) {
            $this->markTestSkipped('Fiber support is not available in this environment.');
        }

        $channel = Channel::make('test-channel', 5);

        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertEquals(5, $channel->getBufferSize());
    }
}
