<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Nova\Fibers\Channel\Channel;
use Nova\Fibers\Facades\Fiber;

echo "Testing full Channel functionality...\n";

try {
    // 创建通道
    $channel = Channel::make('benchmark', 10);
    echo "Channel created successfully\n";
    
    // 启动生产者纤程
    Fiber::run(function () use ($channel) {
        echo "Producer started\n";
        for ($i = 0; $i < 5; $i++) {
            echo "Producing message $i\n";
            $channel->push("Message {$i}");
            usleep(10000); // 0.01秒
        }
        echo "Producer closing channel\n";
        $channel->close();
    });
    
    echo "Producer fiber started\n";
    
    // 消费者循环
    $messages = [];
    echo "Starting consumer loop\n";
    while (($message = $channel->pop(1)) !== null) {
        echo "Consumed: $message\n";
        $messages[] = $message;
    }
    
    echo "Consumer finished\n";
    echo "Messages received: " . count($messages) . "\n";
    echo "Messages: " . implode(', ', $messages) . "\n";
    
    echo "Full Channel test completed successfully\n";
} catch (Exception $e) {
    echo "Full Channel test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}