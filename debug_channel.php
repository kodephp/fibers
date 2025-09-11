<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Nova\Fibers\Channel\Channel;
use Nova\Fibers\Facades\Fiber;

echo "Testing Channel functionality...\n";

try {
    // 创建通道
    $channel = Channel::make('test', 10);
    echo "Channel created successfully\n";
    
    // 测试推送数据
    $result = $channel->push("Hello World");
    echo "Push result: " . ($result ? 'success' : 'failed') . "\n";
    
    // 测试拉取数据
    $data = $channel->pop(1);
    echo "Pop result: " . ($data ?? 'null') . "\n";
    
    echo "Channel test completed successfully\n";
} catch (Exception $e) {
    echo "Channel test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}