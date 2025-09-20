# Channel 详解

## 概述

Channel 是 nova/fibers 提供的纤程间通信组件，它实现了类似于 Go 语言中的通道（channel）功能。Channel 允许在不同的纤程之间安全地传递数据，是构建并发应用的重要工具。

## 基本概念

### 通道

通道是一个可以在纤程之间传递数据的管道，发送方将数据放入通道，接收方从通道中取出数据。

### 缓冲区

通道可以有缓冲区，缓冲区大小决定了通道可以暂存多少数据项。无缓冲通道在发送和接收操作时会同步阻塞。

## 使用方法

### 创建通道

```php
use Nova\Fibers\Channel\Channel;

// 创建无缓冲通道
$channel = Channel::make('task-channel');

// 创建带缓冲区的通道
$bufferedChannel = Channel::make('buffered-channel', 10); // 缓冲区大小为10
```

### 发送和接收数据

```php
use Nova\Fibers\Channel\Channel;
use Nova\Fibers\FiberPool;

// 创建通道
$channel = Channel::make('data-channel', 5);

// 在一个纤程中发送数据
FiberPool::submit(function() use ($channel) {
    for ($i = 1; $i <= 3; $i++) {
        $channel->send("Message $i");
        echo "Sent: Message $i\n";
    }
    $channel->close(); // 关闭通道
});

// 在另一个纤程中接收数据
FiberPool::submit(function() use ($channel) {
    while (($message = $channel->receive()) !== null) {
        echo "Received: $message\n";
    }
    echo "Channel closed\n";
});
```

### 通道操作的超时控制

```php
use Nova\Fibers\Channel\Channel;

$channel = Channel::make('timeout-channel', 2);

// 带超时的发送操作
try {
    $channel->send('data', 1.0); // 1秒超时
    echo "Data sent successfully\n";
} catch (Exception $e) {
    echo "Send timeout: " . $e->getMessage() . "\n";
}

// 带超时的接收操作
try {
    $data = $channel->receive(1.0); // 1秒超时
    if ($data !== null) {
        echo "Received: $data\n";
    } else {
        echo "Channel closed\n";
    }
} catch (Exception $e) {
    echo "Receive timeout: " . $e->getMessage() . "\n";
}
```

## 通道属性

### 通道名称

每个通道都有一个唯一的名称，用于标识通道。

```php
$channel = Channel::make('my-channel');
echo $channel->getName(); // 输出: my-channel
```

### 通道状态

可以检查通道是否已关闭。

```php
$channel = Channel::make('status-channel');

// 检查通道是否已关闭
if ($channel->isClosed()) {
    echo "Channel is closed\n";
} else {
    echo "Channel is open\n";
}

// 关闭通道
$channel->close();

// 再次检查
if ($channel->isClosed()) {
    echo "Channel is closed\n";
}
```

### 缓冲区大小

可以获取通道的缓冲区大小。

```php
$channel = Channel::make('buffer-channel', 5);
echo "Buffer size: " . $channel->getBufferSize(); // 输出: Buffer size: 5
```

## 高级用法

### 通道选择器 (Channel Selector)

Channel Selector 允许同时监听多个通道，当其中任何一个通道可以进行操作时，就会执行相应的处理逻辑。

```php
use Nova\Fibers\Channel\Channel;
use Nova\Fibers\Channel\ChannelSelector;

// 创建多个通道
$channel1 = Channel::make('channel-1', 2);
$channel2 = Channel::make('channel-2', 2);

// 创建选择器
$selector = new ChannelSelector();

// 添加通道到选择器
$selector->addChannel($channel1, ChannelSelector::OPERATION_RECEIVE);
$selector->addChannel($channel2, ChannelSelector::OPERATION_SEND, 'Hello Channel 2');

// 等待通道操作就绪
$operation = $selector->select(5.0); // 5秒超时

if ($operation) {
    switch ($operation->getType()) {
        case ChannelSelector::OPERATION_RECEIVE:
            $data = $operation->getChannel()->receive();
            echo "Received from " . $operation->getChannel()->getName() . ": $data\n";
            break;
        case ChannelSelector::OPERATION_SEND:
            $operation->getChannel()->send($operation->getData());
            echo "Sent to " . $operation->getChannel()->getName() . "\n";
            break;
    }
} else {
    echo "Selector timeout\n";
}
```

### 通道工厂

可以使用通道工厂来统一管理通道的创建和获取。

```php
use Nova\Fibers\Channel\ChannelFactory;

// 获取或创建通道
$channel = ChannelFactory::getChannel('factory-channel', 10);

// 发送数据
$channel->send('Factory message');

// 接收数据
$message = $channel->receive();
echo "Received: $message\n";

// 关闭所有通道
ChannelFactory::closeAllChannels();
```

### 通道中间件

通道支持中间件，可以在数据发送和接收时执行额外的处理逻辑。

```php
use Nova\Fibers\Channel\Channel;

$channel = Channel::make('middleware-channel', 5);

// 添加发送中间件
$channel->addSendMiddleware(function($data) {
    echo "Before sending: $data\n";
    return strtoupper($data); // 转换为大写
});

// 添加接收中间件
$channel->addReceiveMiddleware(function($data) {
    echo "After receiving: $data\n";
    return strtolower($data); // 转换为小写
});

// 发送数据
$channel->send('Hello World');

// 接收数据
$received = $channel->receive();
echo "Final received: $received\n";
```

## 最佳实践

1. **合理设置缓冲区大小**：根据数据流量和处理速度合理设置缓冲区大小，避免缓冲区溢出或资源浪费。
2. **及时关闭通道**：在不再需要通道时及时关闭，释放相关资源。
3. **异常处理**：在通道操作中正确处理可能的异常，如超时异常。
4. **避免死锁**：在使用多个通道时，注意避免死锁情况的发生。
5. **性能监控**：监控通道的使用情况，及时发现和解决性能瓶颈。

## 故障排除

### 通道阻塞

检查是否有纤程在等待通道操作，但没有其他纤程进行相应的发送或接收操作。

### 缓冲区溢出

检查发送到通道的数据量是否超过了缓冲区大小，适当增加缓冲区大小或优化数据处理速度。

### 通道已关闭异常

检查通道是否在使用前已被关闭，确保在通道关闭前完成所有操作。

## 参考资料

- [Go Channels](https://golang.org/doc/effective_go#channels)
- [CSP (Communicating Sequential Processes)](https://en.wikipedia.org/wiki/Communicating_sequential_processes)
- [PHP Fibers RFC](https://wiki.php.net/rfc/fibers)