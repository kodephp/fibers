<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Fibers\Channel\Channel;

echo "Kode/Fibers 通道通信演示\n";
echo "========================\n\n";

// 创建一个通道
$channel = Channel::make('example-channel', 5);

echo "创建通道:\n";
echo "  通道名称: example-channel\n";
echo "  缓冲区大小: 5\n\n";

// 启动生产者纤程
echo "启动生产者纤程...\n";
$fiber1 = new Fiber(function() use ($channel) {
    for ($i = 1; $i <= 10; $i++) {
        echo "  [生产者] 发送数据: Item {$i}\n";
        $channel->push("Item {$i}");
        usleep(100000); // 0.1秒
    }
    echo "  [生产者] 发送完成\n";
    $channel->close();
});

// 启动消费者纤程
echo "启动消费者纤程...\n";
$fiber2 = new Fiber(function() use ($channel) {
    while (true) {
        $data = $channel->pop(1); // 1秒超时
        if ($data === null) {
            echo "  [消费者] 通道已关闭，结束消费\n";
            break;
        }
        echo "  [消费者] 接收数据: {$data}\n";
        usleep(200000); // 0.2秒
    }
});

// 启动纤程
$fiber1->start();
$fiber2->start();

// 运行纤程直到完成
while (!$fiber1->isTerminated() || !$fiber2->isTerminated()) {
    if (!$fiber1->isTerminated()) {
        $fiber1->resume();
    }
    if (!$fiber2->isTerminated()) {
        $fiber2->resume();
    }
    usleep(10000); // 0.01秒
}

echo "\n通道通信演示完成!\n";