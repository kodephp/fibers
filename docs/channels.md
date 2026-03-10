# Channels

本指南将详细介绍 Kode/Fibers 中的通道（Channel）组件，这是 Fiber 之间通信的核心机制。

## 什么是 Channel？

Channel 是一种在 Fiber 之间安全传递数据的机制，类似于 Go 语言中的 Channel。它提供了一种同步机制，允许 Fiber 之间进行通信而不需要共享内存或使用锁。

## 为什么使用 Channel？

在多 Fiber 环境中，直接共享状态可能会导致竞态条件和其他并发问题。Channel 提供了一种更安全、更优雅的方式来处理 Fiber 之间的通信：

- 自动处理 Fiber 之间的同步
- 避免了显式的锁和共享状态
- 支持有缓冲和无缓冲模式
- 提供超时控制功能
- 可以用于实现生产者-消费者模式

## 创建 Channel

### 基本用法

```php
use Kode\Fibers\Channel\Channel;

// 创建一个无缓冲的通道
$channel = Channel::make('data-channel');

// 创建一个有缓冲的通道（缓冲区大小为10）
$channel = Channel::make('data-channel', 10);

// 从配置中创建通道
$channel = Channel::fromConfig([
    'name' => 'data-channel',
    'buffer_size' => 10,
    'timeout' => 5, // 默认超时时间（秒）
    'max_retries' => 3 // 最大重试次数
]);
```

### 全局通道注册表

Kode/Fibers 提供了一个全局通道注册表，可以方便地管理和访问通道实例：

```php
use Kode\Fibers\Channel\ChannelRegistry;

// 注册一个通道
ChannelRegistry::register('notifications', Channel::make('notifications', 100));

// 稍后获取已注册的通道
$channel = ChannelRegistry::get('notifications');

// 检查通道是否已注册
if (ChannelRegistry::has('notifications')) {
    // 通道已注册
}

// 移除已注册的通道
ChannelRegistry::unregister('notifications');

// 获取所有已注册的通道名称
$channelNames = ChannelRegistry::all();
```

## 使用 Channel 进行通信

### 基本的发送和接收

```php
use Kode\Fibers\Channel\Channel;

$channel = Channel::make('message-channel', 5);

// 发送数据到通道
$channel->push('Hello, Fiber!');

// 从通道接收数据
$message = $channel->pop();

// 发送和接收对象
$channel->push(['id' => 1, 'name' => 'User 1']);
$user = $channel->pop();
```

### 带超时的操作

```php
// 发送数据到通道，如果超过2秒未成功则返回false
$success = $channel->push($data, 2);

// 从通道接收数据，如果超过5秒未接收到则返回null
$message = $channel->pop(5);

// 可以使用默认超时值
$channel = Channel::make('data-channel', 10, 3); // 默认超时3秒
$message = $channel->pop(); // 使用默认超时3秒
```

### 非阻塞操作

```php
// 非阻塞发送：如果通道已满，立即返回false而不是等待
$success = $channel->tryPush($data);

// 非阻塞接收：如果通道为空，立即返回null而不是等待
$message = $channel->tryPop();
```

### 检查通道状态

```php
// 检查通道是否已关闭
$isClosed = $channel->isClosed();

// 获取通道中的元素数量
$count = $channel->count();

// 检查通道是否已满
$isFull = $channel->isFull();

// 检查通道是否为空
$isEmpty = $channel->isEmpty();

// 获取通道配置
$config = $channel->getConfig();
```

## 关闭和清理通道

```php
// 关闭通道（不再接受新的发送操作，但仍然可以接收剩余的数据）
$channel->close();

// 清空通道中的所有数据
$channel->clear();
```

## 高级用法

### 生产者-消费者模式

```php
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Facades\Fiber;

// 创建一个缓冲区大小为10的通道
$channel = Channel::make('tasks', 10);

// 启动消费者纤程
Fiber::run(function () use ($channel) {
    while (!$channel->isClosed()) {
        // 从通道接收任务
        $task = $channel->pop(1); // 1秒超时
        
        if ($task !== null) {
            // 处理任务
            processTask($task);
        }
    }
});

// 启动生产者纤程
Fiber::run(function () use ($channel) {
    for ($i = 0; $i < 100; $i++) {
        // 向通道发送任务
        $channel->push(createTask($i));
    }
    
    // 所有任务发送完成后关闭通道
    $channel->close();
});
```

### 工作池模式

```php
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Facades\Fiber;

// 创建任务通道和结果通道
$taskChannel = Channel::make('tasks', 100);
$resultChannel = Channel::make('results', 100);

// 创建工作池（5个工作者）
for ($i = 0; $i < 5; $i++) {
    Fiber::run(function () use ($taskChannel, $resultChannel, $i) {
        echo "Worker $i started\n";
        
        while (true) {
            // 从任务通道接收任务
            $task = $taskChannel->pop();
            
            if ($task === null && $taskChannel->isClosed()) {
                // 通道已关闭且没有更多任务
                echo "Worker $i exiting\n";
                break;
            }
            
            // 处理任务
            $result = processTask($task);
            
            // 发送结果到结果通道
            $resultChannel->push([
                'task' => $task,
                'result' => $result,
                'worker' => $i
            ]);
        }
    });
}

// 发送任务到任务通道
for ($i = 0; $i < 50; $i++) {
    $taskChannel->push(createTask($i));
}

// 关闭任务通道（表示没有更多任务）
$taskChannel->close();

// 收集结果
$results = [];
$expectedResults = 50;

while (count($results) < $expectedResults) {
    $result = $resultChannel->pop();
    $results[] = $result;
}

// 关闭结果通道
$resultChannel->close();

// 处理收集到的结果
processResults($results);
```

### 流水线处理模式

```php
use Kode\Fibers\Channel\Channel;
use Kode\Fibers\Facades\Fiber;

// 创建三个通道，形成流水线
$inputChannel = Channel::make('input', 10);
$processChannel = Channel::make('processed', 10);
$outputChannel = Channel::make('output', 10);

// 阶段1：输入处理
Fiber::run(function () use ($inputChannel, $processChannel) {
    while (true) {
        $rawData = $inputChannel->pop();
        
        if ($rawData === null && $inputChannel->isClosed()) {
            $processChannel->close();
            break;
        }
        
        // 处理原始数据
        $processedData = preprocessData($rawData);
        $processChannel->push($processedData);
    }
});

// 阶段2：核心处理
Fiber::run(function () use ($processChannel, $outputChannel) {
    while (true) {
        $processedData = $processChannel->pop();
        
        if ($processedData === null && $processChannel->isClosed()) {
            $outputChannel->close();
            break;
        }
        
        // 核心业务处理
        $result = processCoreBusinessLogic($processedData);
        $outputChannel->push($result);
    }
});

// 阶段3：输出处理
Fiber::run(function () use ($outputChannel) {
    while (true) {
        $result = $outputChannel->pop();
        
        if ($result === null && $outputChannel->isClosed()) {
            break;
        }
        
        // 处理结果（如保存到数据库、发送通知等）
        finalizeResult($result);
    }
});

// 发送数据到流水线
for ($i = 0; $i < 100; $i++) {
    $inputChannel->push(getRawData($i));
}

// 关闭输入通道，表示没有更多数据
$inputChannel->close();
```

## 最佳实践

### 通道大小选择

- **无缓冲通道**：适用于需要严格同步的场景
- **小缓冲通道**（1-10）：适用于大多数一般场景
- **大缓冲通道**（100+）：适用于生产者和消费者速度不匹配的场景

### 超时设置

- 始终为长时间运行的操作设置合理的超时时间
- 对于关键任务，使用较短的超时时间以便快速失败和重试
- 对于非关键任务，可以使用较长的超时时间

### 错误处理

- 使用 try/catch 包裹通道操作，处理可能的异常
- 对于超时操作，实现适当的回退逻辑
- 定期检查通道状态，避免在已关闭的通道上操作

### 资源管理

- 不再使用的通道应及时关闭，释放资源
- 避免创建过多的通道，可能导致资源浪费
- 对于长时间运行的应用，定期检查和清理闲置通道

## 常见问题

### Q: 为什么我的 Fiber 在 `pop()` 或 `push()` 操作时被卡住了？

A: 这可能是因为：
- 对于无缓冲通道，没有其他 Fiber 在等待接收或发送数据
- 对于有缓冲通道，缓冲区已满（push）或为空（pop），且没有其他 Fiber 在操作通道
- 忘记关闭通道，导致 Fiber 一直等待

### Q: 我可以在非 Fiber 环境中使用 Channel 吗？

A: 是的，但在非 Fiber 环境中，通道操作可能会阻塞主线程，失去了使用通道的主要优势。建议在 Fiber 环境中使用通道。

### Q: 通道可以传递多大的数据？

A: 理论上，通道可以传递任何大小的数据，但大型数据可能会增加内存使用和复制成本。对于大型数据，建议传递引用或指针而不是实际数据。

### Q: 如何优雅地关闭多个相互依赖的通道？

A: 建议使用层次化的关闭顺序，先关闭最上游的通道，然后依次关闭下游的通道。可以在每个 Fiber 中检查通道状态，当上游通道关闭且没有更多数据时，关闭下游通道。

## 下一步

- 查看 [框架集成](framework-integration.md) 文档了解如何在不同框架中使用通道
- 查看 [任务管理](task-management.md) 文档了解如何结合通道和任务进行更复杂的工作流
- 查看 [通道示例](../examples/channel_example.php) 了解更多通道的实际用法