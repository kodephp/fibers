# EventLoop 开发指南

## 概述

EventLoop 是 nova/fibers 包的核心组件之一，它提供了一套完整的事件驱动编程接口。通过 EventLoop，开发者可以处理异步操作，如定时任务、I/O 操作和信号处理等。

## 核心概念

### 事件循环生命周期

事件循环控制程序的执行流程，一旦启动，它将持续运行直到：

1. 显式调用 `stop()` 方法停止
2. 程序出现未处理的错误
3. 没有更多任务需要处理
4. 被外部信号中断

### 事件类型

EventLoop 支持多种事件类型：

1. **Defer** - 在下一次事件循环迭代中执行
2. **Delay** - 延迟一段时间后执行
3. **Repeat** - 重复执行的任务
4. **Stream** - 流事件（可读/可写）
5. **Signal** - 信号事件

## 详细使用指南

### 1. Defer 延迟执行

Defer 用于将回调函数安排在事件循环的下一次迭代中执行。

```php
use Nova\Fibers\Core\EventLoop;

// 立即返回，但回调将在下一次循环迭代中执行
EventLoop::defer(function() {
    echo "Deferred execution\n";
});

echo "Immediate execution\n";

// 手动处理一次循环迭代（仅用于演示）
$eventLoop = EventLoop::getInstance();
$reflection = new ReflectionClass($eventLoop);
$method = $reflection->getMethod('tick');
$method->setAccessible(true);
$method->invoke($eventLoop);
```

输出：
```
Immediate execution
Deferred execution
```

### 2. Delay 延迟定时器

Delay 用于在指定的时间后执行回调函数。

```php
use Nova\Fibers\Core\EventLoop;

// 在1.5秒后执行回调
$timerId = EventLoop::delay(1.5, function() {
    echo "Executed after 1.5 seconds\n";
});

// 可以取消定时器
// EventLoop::cancel($timerId);
```

### 3. Repeat 重复定时器

Repeat 用于以指定的间隔重复执行回调函数。

```php
use Nova\Fibers\Core\EventLoop;

$count = 0;

// 每2秒执行一次回调
$timerId = EventLoop::repeat(2.0, function() use (&$count) {
    echo "Executed " . (++$count) . " times\n";
    
    // 执行5次后取消
    if ($count >= 5) {
        EventLoop::cancel($timerId);
        echo "Timer cancelled\n";
    }
});
```

### 4. Stream 流事件

Stream 事件用于处理 I/O 操作，如网络通信和文件操作。

#### 可读流事件

```php
use Nova\Fibers\Core\EventLoop;

// 创建一个TCP连接
$socket = stream_socket_client("tcp://httpbin.org:80", $errno, $errstr, 30);
if ($socket) {
    stream_set_blocking($socket, false);
    
    // 发送HTTP请求
    $request = "GET /json HTTP/1.1\r\nHost: httpbin.org\r\nConnection: close\r\n\r\n";
    fwrite($socket, $request);
    
    // 监听可读事件
    EventLoop::onReadable($socket, function($stream) {
        $data = fread($stream, 1024);
        if ($data !== false && strlen($data) > 0) {
            echo "Received: " . strlen($data) . " bytes\n";
        } else {
            echo "Connection closed\n";
            EventLoop::cancelStream($stream);
        }
    });
}
```

#### 可写流事件

```php
use Nova\Fibers\Core\EventLoop;

// 创建一个TCP连接
$socket = stream_socket_client("tcp://httpbin.org:80", $errno, $errstr, 30);
if ($socket) {
    stream_set_blocking($socket, false);
    
    // 监听可写事件
    EventLoop::onWritable($socket, function($stream) {
        $request = "GET /json HTTP/1.1\r\nHost: httpbin.org\r\nConnection: close\r\n\r\n";
        $written = fwrite($stream, $request);
        if ($written !== false) {
            echo "Sent $written bytes\n";
            EventLoop::cancelStream($stream);
            
            // 现在监听可读事件以接收响应
            EventLoop::onReadable($stream, function($stream) {
                $data = fread($stream, 1024);
                if ($data !== false && strlen($data) > 0) {
                    echo "Received: " . strlen($data) . " bytes\n";
                } else {
                    echo "Connection closed\n";
                    EventLoop::cancelStream($stream);
                }
            });
        }
    });
}
```

### 5. Signal 信号处理

Signal 用于处理操作系统信号。

```php
use Nova\Fibers\Core\EventLoop;

// 处理 SIGINT (Ctrl+C)
EventLoop::onSignal(SIGINT, function($signal) {
    echo "Received SIGINT, stopping event loop\n";
    EventLoop::stop();
});

// 处理 SIGTERM
EventLoop::onSignal(SIGTERM, function($signal) {
    echo "Received SIGTERM, stopping event loop\n";
    EventLoop::stop();
});
```

## 高级用法示例

### 异步 HTTP 客户端

```php
use Nova\Fibers\Core\EventLoop;

class AsyncHttpClient 
{
    private array $requests = [];
    
    public function get(string $url, callable $callback): void 
    {
        $this->request('GET', $url, null, $callback);
    }
    
    public function post(string $url, string $data, callable $callback): void 
    {
        $this->request('POST', $url, $data, $callback);
    }
    
    private function request(string $method, string $url, ?string $data, callable $callback): void 
    {
        // 解析URL
        $parts = parse_url($url);
        if (!$parts) {
            $callback(null, new Exception("Invalid URL: $url"));
            return;
        }
        
        $host = $parts['host'];
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $path = $parts['path'] ?? '/';
        
        // 创建连接
        $socket = stream_socket_client("tcp://$host:$port", $errno, $errstr, 30);
        if (!$socket) {
            $callback(null, new Exception("Connection failed: $errstr ($errno)"));
            return;
        }
        
        stream_set_blocking($socket, false);
        
        $request = "$method $path HTTP/1.1\r\n";
        $request .= "Host: $host\r\n";
        $request .= "Connection: close\r\n";
        
        if ($data !== null) {
            $request .= "Content-Length: " . strlen($data) . "\r\n";
            $request .= "\r\n";
            $request .= $data;
        } else {
            $request .= "\r\n";
        }
        
        // 发送请求
        EventLoop::onWritable($socket, function($stream) use ($request, $callback) {
            fwrite($stream, $request);
            EventLoop::cancelStream($stream);
            
            // 接收响应
            $response = '';
            EventLoop::onReadable($stream, function($stream) use (&$response, $callback) {
                $data = fread($stream, 8192);
                if ($data !== false && strlen($data) > 0) {
                    $response .= $data;
                } else {
                    EventLoop::cancelStream($stream);
                    $callback($response, null);
                }
            });
        });
    }
}

// 使用示例
$client = new AsyncHttpClient();

$client->get('http://httpbin.org/json', function($response, $error) {
    if ($error) {
        echo "Error: " . $error->getMessage() . "\n";
    } else {
        echo "Response: " . strlen($response) . " bytes\n";
        // 解析JSON响应
        $data = json_decode($response, true);
        if ($data) {
            echo "Received JSON data\n";
        }
    }
    EventLoop::stop();
});

// 运行事件循环
EventLoop::run();
```

### 定时任务调度器

```php
use Nova\Fibers\Core\EventLoop;

class TaskScheduler 
{
    private array $tasks = [];
    
    public function addTask(string $name, float $interval, callable $task): void 
    {
        $this->tasks[$name] = [
            'interval' => $interval,
            'task' => $task,
            'timer_id' => null
        ];
        
        $this->scheduleTask($name);
    }
    
    public function removeTask(string $name): void 
    {
        if (isset($this->tasks[$name]['timer_id'])) {
            EventLoop::cancel($this->tasks[$name]['timer_id']);
        }
        unset($this->tasks[$name]);
    }
    
    private function scheduleTask(string $name): void 
    {
        if (!isset($this->tasks[$name])) {
            return;
        }
        
        $task = $this->tasks[$name];
        $this->tasks[$name]['timer_id'] = EventLoop::repeat($task['interval'], function() use ($name, $task) {
            try {
                $task['task']();
            } catch (Exception $e) {
                echo "Task $name error: " . $e->getMessage() . "\n";
            }
        });
    }
}

// 使用示例
$scheduler = new TaskScheduler();

// 添加定时任务
$scheduler->addTask('heartbeat', 5.0, function() {
    echo "Heartbeat at " . date('Y-m-d H:i:s') . "\n";
});

$scheduler->addTask('cleanup', 30.0, function() {
    echo "Performing cleanup at " . date('Y-m-d H:i:s') . "\n";
});

// 30秒后停止
EventLoop::delay(30.0, function() {
    echo "Stopping scheduler\n";
    EventLoop::stop();
});

// 运行事件循环
EventLoop::run();
```

## 最佳实践

### 1. 错误处理

始终在回调函数中使用 try-catch 块处理可能的异常：

```php
EventLoop::defer(function() {
    try {
        // 可能抛出异常的代码
        riskyOperation();
    } catch (Exception $e) {
        // 记录错误但不要中断事件循环
        error_log("Error in deferred callback: " . $e->getMessage());
    }
});
```

### 2. 资源清理

使用相应的取消方法清理不再需要的监听器：

```php
// 取消定时器
EventLoop::cancel($timerId);

// 取消流监听
EventLoop::cancelStream($stream);

// 取消信号监听
EventLoop::cancelSignal($signal);
```

### 3. 避免阻塞操作

确保回调函数不会执行长时间运行的同步操作：

```php
// 错误示例 - 阻塞操作
EventLoop::defer(function() {
    sleep(10); // 这会阻塞整个事件循环
});

// 正确示例 - 非阻塞操作
EventLoop::delay(10.0, function() {
    // 这不会阻塞事件循环
});
```

### 4. 内存管理

及时清理已完成任务的相关数据，避免内存泄漏：

```php
// 定时清理已完成的任务数据
EventLoop::repeat(60.0, function() use (&$taskResults) {
    // 清理旧的任务结果
    $taskResults = array_slice($taskResults, -100); // 只保留最近100个结果
});
```

## API 参考

### 静态方法

| 方法 | 描述 |
|------|------|
| `EventLoop::defer(callable $callback): void` | 在下一次事件循环迭代中执行回调 |
| `EventLoop::delay(float $delay, callable $callback): string` | 在指定延迟后执行回调，返回定时器ID |
| `EventLoop::repeat(float $interval, callable $callback): string` | 以指定间隔重复执行回调，返回定时器ID |
| `EventLoop::onReadable(resource $stream, callable $callback): void` | 监听流的可读事件 |
| `EventLoop::onWritable(resource $stream, callable $callback): void` | 监听流的可写事件 |
| `EventLoop::onSignal(int $signal, callable $callback): void` | 监听指定信号 |
| `EventLoop::cancel(string $timerId): void` | 取消定时器（delay或repeat）|
| `EventLoop::cancelStream(resource $stream): void` | 停止监听流事件 |
| `EventLoop::cancelSignal(int $signal): void` | 停止监听信号 |
| `EventLoop::run(): void` | 启动事件循环 |
| `EventLoop::stop(): void` | 停止事件循环 |
| `EventLoop::getInstance(): EventLoop` | 获取单例实例 |

## 注意事项

1. 事件循环会接管程序的执行流程，直到被停止或没有更多任务需要处理。

2. 在使用信号处理功能时，需要确保 PHP 的 pcntl 扩展可用。

3. 流事件处理需要将流设置为非阻塞模式。

4. 事件循环在没有任务时会短暂休眠以避免占用过多 CPU 资源。

5. 定时器的精度取决于系统的调度精度，不能保证完全精确的时间控制。

通过遵循这些指南和示例，您可以充分利用 EventLoop 的功能来构建高性能的异步应用程序。