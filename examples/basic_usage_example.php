<?php

/**
 * Basic Usage Example
 * 
 * This example demonstrates the basic usage of the nova/fibers package:
 * - Simple fiber execution
 * - Fiber pool usage
 * - Channel communication
 * - Event handling
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Facades\Fiber;
use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Channel\Channel;
use Nova\Fibers\Event\EventBus;
use Nova\Fibers\Context\Context;
use Nova\Fibers\Context\ContextManager;

echo "=== Basic Usage Example ===\n\n";

// 1. Simple fiber execution
echo "1. Simple Fiber Execution:\n";
$result = Fiber::run(function() {
    usleep(100000); // 100ms
    return "Hello from Fiber!";
});

echo "  Result: {$result}\n\n";

// 2. Fiber pool usage
echo "2. Fiber Pool Usage:\n";
$pool = new FiberPool([
    'size' => 4,
    'name' => 'example-pool'
]);

$tasks = [];
for ($i = 1; $i <= 3; $i++) {
    $tasks[] = function() use ($i) {
        usleep(100000); // 100ms
        return "Task {$i} completed";
    };
}

$results = $pool->concurrent($tasks);
foreach ($results as $index => $result) {
    echo "  {$result}\n";
}
echo "\n";

// 3. Channel communication
echo "3. Channel Communication:\n";
$channel = Channel::make('example-channel', 2);

// Producer fiber
$producer = new \Fiber(function() use ($channel) {
    for ($i = 1; $i <= 3; $i++) {
        echo "  Sending message {$i}\n";
        $channel->push("Message {$i}");
        usleep(50000); // 50ms
    }
    $channel->close();
});

// Consumer fiber
$consumer = new \Fiber(function() use ($channel) {
    while (($message = $channel->pop(1.0)) !== false) { // 1 second timeout
        echo "  Received: {$message}\n";
        usleep(50000); // 50ms
    }
});

$producer->start();
$consumer->start();

// Wait for fibers to complete
while ($producer->isTerminated() === false || $consumer->isTerminated() === false) {
    usleep(10000); // 10ms
}
echo "\n";

// 4. Event handling
echo "4. Event Handling:\n";

// 定义一个简单的事件类
class UserEvent {
    public $type;
    public $data;
    
    public function __construct($type, $data) {
        $this->type = $type;
        $this->data = $data;
    }
}

EventBus::on('user.registered', function($event) {
    echo "  User registered: {$event->data['name']} (ID: {$event->data['id']})\n";
});

EventBus::on('user.activated', function($event) {
    echo "  User activated: {$event->data['name']} (ID: {$event->data['id']})\n";
});

// Fire events
EventBus::fire(new UserEvent('user.registered', ['id' => 1, 'name' => 'John Doe']));
EventBus::fire(new UserEvent('user.activated', ['id' => 1, 'name' => 'John Doe']));
echo "\n";

// 5. Context usage
echo "5. Context Usage:\n";

// Initialize ContextManager if not already initialized
if (!ContextManager::getCurrentContext()) {
    ContextManager::setCurrentContext(new Context('main_context'));
}

// Set initial context values
$context = ContextManager::getCurrentContext();
if ($context) {
    $context = $context->withValue('user_id', 123);
    $context = $context->withValue('session_id', uniqid('sess_'));
    $context = $context->withValue('request_time', date('Y-m-d H:i:s'));
    ContextManager::setCurrentContext($context);
} else {
    $context = new Context('main_context');
    $context = $context->withValue('user_id', 123);
    $context = $context->withValue('session_id', uniqid('sess_'));
    $context = $context->withValue('request_time', date('Y-m-d H:i:s'));
    ContextManager::setCurrentContext($context);
}

echo "  Initial context:\n";
echo "    User ID: " . $context->value('user_id') . "\n";
echo "    Session ID: " . $context->value('session_id') . "\n";
echo "    Request Time: " . $context->value('request_time') . "\n";

// Access context in a fiber
$fiberWithContext = new \Fiber(function() {
    $context = ContextManager::getCurrentContext();
    if ($context) {
        echo "  Fiber context:\n";
        echo "    User ID: " . $context->value('user_id') . "\n";
        echo "    Session ID: " . $context->value('session_id') . "\n";
        echo "    Request Time: " . $context->value('request_time') . "\n";
        
        // Modify context
        $context = $context->withValue('fiber_executed', true);
        $context = $context->withValue('fiber_result', 'Success');
        ContextManager::setCurrentContext($context);
    }
});

$fiberWithContext->start();

// Check updated context
$updatedContext = ContextManager::getCurrentContext();
if ($updatedContext) {
    echo "  Updated context:\n";
    echo "    Fiber Executed: " . ($updatedContext->value('fiber_executed') ? 'Yes' : 'No') . "\n";
    echo "    Fiber Result: " . $updatedContext->value('fiber_result', 'N/A') . "\n";
} else {
    echo "  Updated context: N/A\n";
}

echo "\n=== Basic Usage Example Completed ===\n";