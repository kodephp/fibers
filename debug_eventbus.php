<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Nova\Fibers\Event\EventBus;

echo "Testing EventBus functionality...\n";

// 定义一个简单的事件类
class TestEvent {
    public function __construct(
        public int $id,
        public string $message
    ) {}
}

try {
    $eventCount = 0;
    
    // 注册事件监听器
    EventBus::on(TestEvent::class, function (TestEvent $event) use (&$eventCount) {
        echo "Event received: ID={$event->id}, Message={$event->message}\n";
        $eventCount++;
    });
    
    echo "Event listener registered\n";
    
    // 触发事件
    EventBus::fire(new TestEvent(1, 'Hello World'));
    EventBus::fire(new TestEvent(2, 'Another event'));
    
    echo "Events fired\n";
    echo "Events processed: {$eventCount}\n";
    
    echo "EventBus test completed successfully\n";
} catch (Exception $e) {
    echo "EventBus test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}