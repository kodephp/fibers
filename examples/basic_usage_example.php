<?php

/**
 * Basic Usage Example
 * 
 * This example demonstrates the basic usage of the nova/fibers package:
 * - Simple fiber execution
 * - Fiber pool usage
 * - Channel communication
 * - Context passing
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Context\Context;
use Nova\Fibers\Context\ContextManager;

echo "=== Basic Usage Example ===\n\n";

// 1. Simple Fiber Execution
echo "1. Simple Fiber Execution:\n";
$fiber = new Fiber(function () {
    echo "  Inside fiber\n";
    Fiber::suspend();
    echo "  Resumed fiber\n";
    return "Fiber result";
});

$fiber->start();
$fiber->resume();
$result = $fiber->getReturn();
echo "  Result: {$result}\n\n";

// 2. Fiber Pool Usage
echo "2. Fiber Pool Usage:\n";
// Note: In a real application, you would use the actual FiberPool implementation
// For this example, we'll simulate the usage

class SimpleFiberPool {
    private $size;
    private $fibers = [];
    
    public function __construct($size = 4) {
        $this->size = $size;
    }
    
    public function submit(callable $task) {
        $fiber = new Fiber($task);
        $this->fibers[] = $fiber;
        return $fiber;
    }
    
    public function run() {
        foreach ($this->fibers as $fiber) {
            if (!$fiber->isStarted()) {
                $fiber->start();
            } elseif ($fiber->isSuspended()) {
                $fiber->resume();
            }
        }
        
        // Wait for all fibers to complete
        foreach ($this->fibers as $fiber) {
            while ($fiber->isRunning()) {
                Fiber::suspend();
            }
        }
    }
}

$pool = new SimpleFiberPool(3);

$tasks = [];
for ($i = 1; $i <= 3; $i++) {
    $taskId = $i;
    $pool->submit(function() use ($taskId) {
        echo "  Task {$taskId} started\n";
        usleep(100000); // 100ms
        echo "  Task {$taskId} completed\n";
        return "Result from task {$taskId}";
    });
}

echo "  Starting fiber pool...\n";
$pool->run();
echo "  Fiber pool completed\n\n";

// 3. Channel Communication
echo "3. Channel Communication:\n";
// Note: In a real application, you would use the actual Channel implementation
// For this example, we'll simulate a simple channel

class SimpleChannel {
    private $queue = [];
    private $closed = false;
    
    public function push($data) {
        if ($this->closed) {
            throw new RuntimeException("Cannot push to closed channel");
        }
        $this->queue[] = $data;
    }
    
    public function pop() {
        if (empty($this->queue) && $this->closed) {
            return null;
        }
        
        while (empty($this->queue) && !$this->closed) {
            Fiber::suspend();
        }
        
        return array_shift($this->queue);
    }
    
    public function close() {
        $this->closed = true;
    }
}

$channel = new SimpleChannel();

// Producer fiber
$producer = new Fiber(function() use ($channel) {
    for ($i = 1; $i <= 3; $i++) {
        echo "  Sending message {$i}\n";
        $channel->push("Message {$i}");
        usleep(50000); // 50ms
    }
    $channel->close();
    echo "  Producer finished\n";
});

// Consumer fiber
$consumer = new Fiber(function() use ($channel) {
    while (true) {
        $message = $channel->pop();
        if ($message === null) {
            break;
        }
        echo "  Received: {$message}\n";
        usleep(30000); // 30ms
    }
    echo "  Consumer finished\n";
});

$producer->start();
$consumer->start();

// Run both fibers until completion
while ($producer->isRunning() || $consumer->isRunning()) {
    if ($producer->isSuspended()) {
        $producer->resume();
    }
    if ($consumer->isSuspended()) {
        $consumer->resume();
    }
    usleep(10000); // 10ms
}

echo "\n";

// 4. Context Passing
echo "4. Context Passing:\n";

// Create a context
$context = new Context('main_context');
$context = $context->withValue('user_id', 42);
$context = $context->withValue('session_id', 'sess_' . uniqid());
$context = $context->withValue('request_time', time());

// Set the context as current
ContextManager::setCurrentContext($context);

// Access context in main thread
echo "  Main thread context:\n";
echo "    User ID: " . $context->value('user_id') . "\n";
echo "    Session ID: " . $context->value('session_id') . "\n";
echo "    Request Time: " . $context->value('request_time') . "\n";

// Access context in a fiber
$fiberWithContext = new Fiber(function() {
    $context = ContextManager::getCurrentContext();
    echo "  Fiber context:\n";
    echo "    User ID: " . $context->value('user_id') . "\n";
    echo "    Session ID: " . $context->value('session_id') . "\n";
    echo "    Request Time: " . $context->value('request_time') . "\n";
    
    // Modify context
    $context = $context->withValue('fiber_executed', true);
    $context = $context->withValue('fiber_result', 'Success');
    ContextManager::setCurrentContext($context);
});

$fiberWithContext->start();

// Check updated context
$updatedContext = ContextManager::getCurrentContext();
echo "  Updated context:\n";
echo "    Fiber Executed: " . ($updatedContext->value('fiber_executed') ? 'Yes' : 'No') . "\n";
echo "    Fiber Result: " . $updatedContext->value('fiber_result', 'N/A') . "\n";

echo "\n=== Basic Usage Example Completed ===\n";