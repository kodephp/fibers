# EventLoop 详解

## 概述

EventLoop 是 nova/fibers 的核心组件之一，负责驱动纤程的执行和调度。它基于 `revolt/event-loop` 实现，提供了完整的事件驱动机制。

## 基本概念

### 事件循环

事件循环是程序中一个无限循环，用于等待和分发事件或消息。在 nova/fibers 中，EventLoop 负责处理各种异步操作，如定时器、I/O 事件等。

### 纤程与事件循环

每个纤程都在事件循环中运行，事件循环负责在纤程之间进行切换和调度。当一个纤程需要等待某个操作完成时（如 I/O 操作），它会挂起并让出控制权给事件循环，事件循环会继续执行其他纤程。

## 使用方法

### defer

`defer` 方法用于在下一次事件循环迭代中执行回调函数。

```php
use Nova\Fibers\Core\EventLoop;

EventLoop::defer(function() {
    echo "Deferred execution\n";
});
```

### delay

`delay` 方法用于延迟执行回调函数。

```php
use Nova\Fibers\Core\EventLoop;

EventLoop::delay(1.0, function() {
    echo "Executed after 1 second\n";
});
```

### repeat

`repeat` 方法用于重复执行回调函数。

```php
use Nova\Fibers\Core\EventLoop;

$timerId = EventLoop::repeat(2.0, function() {
    echo "Executed every 2 seconds\n";
});

// 可以取消重复执行
// EventLoop::cancel($timerId);
```

### onReadable

`onReadable` 方法用于监听可读流事件。

```php
use Nova\Fibers\Core\EventLoop;

$socket = stream_socket_client("tcp://example.com:80");
stream_set_blocking($socket, false);

EventLoop::onReadable($socket, function($stream) {
    $data = fread($stream, 1024);
    if ($data !== false) {
        echo "Received: $data\n";
    } else {
        echo "Connection closed\n";
        EventLoop::cancelStream($stream);
    }
});
```

### onWritable

`onWritable` 方法用于监听可写流事件。

```php
use Nova\Fibers\Core\EventLoop;

EventLoop::onWritable($socket, function($stream) {
    $data = "GET / HTTP/1.1\r\nHost: example.com\r\n\r\n";
    fwrite($stream, $data);
    EventLoop::cancelStream($stream);
});
```

### onSignal

`onSignal` 方法用于监听信号事件。

```php
use Nova\Fibers\Core\EventLoop;

EventLoop::onSignal(SIGINT, function($signal) {
    echo "Received SIGINT, stopping\n";
    EventLoop::stop();
});
```

### run

`run` 方法用于启动事件循环。

```php
use Nova\Fibers\Core\EventLoop;

EventLoop::run();
```

### stop

`stop` 方法用于停止事件循环。

```php
use Nova\Fibers\Core\EventLoop;

EventLoop::stop();
```

## 高级用法

### 自定义事件循环

nova/fibers 允许您使用自定义的事件循环实现。您可以通过实现 `Nova\Fibers\Contracts\EventLoopInterface` 接口来创建自己的事件循环。

```php
use Nova\Fibers\Contracts\EventLoopInterface;

class CustomEventLoop implements EventLoopInterface
{
    public function defer(callable $callback): string
    {
        // 实现 defer 方法
    }

    public function delay(float $delay, callable $callback): string
    {
        // 实现 delay 方法
    }

    public function repeat(float $interval, callable $callback): string
    {
        // 实现 repeat 方法
    }

    public function onReadable($stream, callable $callback): string
    {
        // 实现 onReadable 方法
    }

    public function onWritable($stream, callable $callback): string
    {
        // 实现 onWritable 方法
    }

    public function onSignal(int $signal, callable $callback): string
    {
        // 实现 onSignal 方法
    }

    public function cancel(string $id): void
    {
        // 实现 cancel 方法
    }

    public function cancelStream($stream): void
    {
        // 实现 cancelStream 方法
    }

    public function run(): void
    {
        // 实现 run 方法
    }

    public function stop(): void
    {
        // 实现 stop 方法
    }
}
```

然后在配置文件中指定使用自定义事件循环：

```php
// config/fibers.php
return [
    'event_loop' => CustomEventLoop::class,
    // 其他配置...
];
```

## 最佳实践

1. **避免阻塞操作**：在事件循环中执行的代码应该是非阻塞的，避免长时间运行的同步操作。
2. **合理使用定时器**：定时器是事件循环的重要组成部分，但过多的定时器会影响性能。
3. **正确处理异常**：在事件循环中执行的代码可能会抛出异常，需要正确处理以避免事件循环崩溃。
4. **资源清理**：及时清理不再需要的资源，如取消定时器、关闭流等。

## 故障排除

### 事件循环不运行

确保调用了 `EventLoop::run()` 方法来启动事件循环。

### 定时器不触发

检查定时器的延迟时间是否正确，以及事件循环是否正常运行。

### 流事件不触发

确保流处于正确的状态（可读或可写），并且事件循环正在运行。

## 参考资料

- [PHP Fibers RFC](https://wiki.php.net/rfc/fibers)
- [RevoltPHP Event Loop](https://github.com/revoltphp/event-loop)