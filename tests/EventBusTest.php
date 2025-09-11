<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Event\EventBus;

/**
 * EventBus 测试类
 *
 * @package Nova\Fibers\Tests
 */
class EventBusTest extends TestCase
{
    /**
     * 设置测试环境
     *
     * @return void
     */
    protected function setUp(): void
    {
        // 清理事件总线
        EventBus::reset();
    }

    /**
     * 测试事件监听器注册和触发
     *
     * @covers \Nova\Fibers\Event\EventBus::on
     * @covers \Nova\Fibers\Event\EventBus::fire
     * @return void
     */
    public function testEventRegistrationAndFiring(): void
    {
        // 创建测试事件类
        if (!class_exists('TestEvent')) {
            eval('
                class TestEvent {
                    public string $data;
                    public function __construct(string $data) {
                        $this->data = $data;
                    }
                }
            ');
        }

        $result = [];

        // 注册事件监听器
        EventBus::on('TestEvent', function ($event) use (&$result) {
            $result[] = $event->data;
        });

        // 创建事件对象
        $event = new \TestEvent('test data');

        // 触发事件
        EventBus::fire($event);

        // 验证监听器被调用
        $this->assertCount(1, $result);
        $this->assertEquals('test data', $result[0]);
    }

    /**
     * 测试移除事件监听器
     *
     * @covers \Nova\Fibers\Event\EventBus::on
     * @covers \Nova\Fibers\Event\EventBus::off
     * @return void
     */
    public function testRemoveEventListener(): void
    {
        // 创建测试事件类
        if (!class_exists('AnotherTestEvent')) {
            eval('
                class AnotherTestEvent {
                    public string $data;
                    public function __construct(string $data) {
                        $this->data = $data;
                    }
                }
            ');
        }

        $result = [];

        // 注册事件监听器
        $listener = function ($event) use (&$result) {
            $result[] = $event->data;
        };

        EventBus::on('AnotherTestEvent', $listener);

        // 创建事件对象
        $event1 = new \AnotherTestEvent('test data 1');

        // 触发事件
        EventBus::fire($event1);

        // 移除监听器
        EventBus::off('AnotherTestEvent', $listener);

        // 创建另一个事件对象
        $event2 = new \AnotherTestEvent('test data 2');

        // 再次触发事件
        EventBus::fire($event2);

        // 验证监听器只被调用一次
        $this->assertCount(1, $result);
        $this->assertEquals('test data 1', $result[0]);
    }

    /**
     * 测试获取监听器数量
     *
     * @covers \Nova\Fibers\Event\EventBus::on
     * @covers \Nova\Fibers\Event\EventBus::listenerCount
     * @return void
     */
    public function testGetListenerCount(): void
    {
        // 定义测试事件类
        $testEventClass = 'TestEvent';
        $anotherEventClass = 'AnotherEvent';

        // 注册多个事件监听器
        EventBus::on($testEventClass, function () {
        });
        EventBus::on($testEventClass, function () {
        });
        EventBus::on($anotherEventClass, function () {
        });

        // 验证监听器数量
        $this->assertEquals(2, EventBus::listenerCount($testEventClass));
        $this->assertEquals(1, EventBus::listenerCount($anotherEventClass));
        $this->assertEquals(0, EventBus::listenerCount('nonexistent.event'));
    }
}
