<?php

/**
 * EventLoop Example
 * 
 * This example demonstrates the usage of the EventLoop functionality:
 * - Defer
 * - Delay
 * - Repeat
 * - Stream events
 * - Signal handling
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Core\EventLoop;
use Nova\Fibers\Support\Environment;

// 检查环境是否支持Fiber
if (!Environment::supportsFibers()) {
    echo "Error: Fibers are not supported in this environment!\n";
    exit(1);
}

echo "=== EventLoop Example ===\n\n";

// 1. Defer example
echo "1. Defer Example:\n";
EventLoop::defer(function() {
    echo "  This is deferred execution\n";
});

echo "  This is immediate execution\n";

// Process defer queue manually for demo
$eventLoop = EventLoop::getInstance();
$reflection = new \ReflectionClass($eventLoop);
$method = $reflection->getMethod('processDeferQueue');
$method->setAccessible(true);
$method->invoke($eventLoop);

echo "\n";

// 2. Delay example
echo "2. Delay Example:\n";
EventLoop::delay(0.1, function() {
    echo "  This is delayed execution (after 0.1 seconds)\n";
});

echo "  This is immediate execution\n";

// Wait for delay to complete
usleep(150000); // 150ms

// Process timers manually for demo
$method = $reflection->getMethod('processTimers');
$method->setAccessible(true);
$method->invoke($eventLoop);

echo "\n";

// 3. Repeat example
echo "3. Repeat Example:\n";
$count = 0;
$timerId = EventLoop::repeat(0.05, function() use (&$count, &$timerId) {
    echo "  Repeated execution #" . (++$count) . "\n";
    
    if ($count >= 3) {
        EventLoop::cancel($timerId);
        echo "  Stopping repeat execution\n";
    }
});

// Process repeat timers manually for demo
$method = $reflection->getMethod('processRepeatTimers');
$method->setAccessible(true);

for ($i = 0; $i < 5; $i++) {
    $method->invoke($eventLoop);
    usleep(60000); // 60ms
}

echo "\n";

// 4. Stream example
echo "4. Stream Example:\n";
$tempFile = tempnam(sys_get_temp_dir(), 'eventloop_test');
$stream = fopen($tempFile, 'w');
stream_set_blocking($stream, false);

EventLoop::onWritable($stream, function($stream) {
    echo "  Stream is writable\n";
    fwrite($stream, "Hello EventLoop!");
});

// Process streams manually for demo
usleep(10000);
$method = $reflection->getMethod('processStreams');
$method->setAccessible(true);
$method->invoke($eventLoop);

fclose($stream);
unlink($tempFile);

echo "\n";

echo "=== EventLoop Example Completed ===\n";