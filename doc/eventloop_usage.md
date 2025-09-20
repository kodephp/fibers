# EventLoop 使用指南

## 概述

EventLoop 是 nova/fibers 包中的一个核心组件，它提供了一个基于 PHP Fiber 的事件循环系统。通过 EventLoop，您可以异步处理各种事件，包括延迟执行、重复执行、流事件和信号处理等。

## 安装和设置

EventLoop 是 nova/fibers 包的一部分，安装包后即可使用：

```bash
composer require nova/fibers
```

## 基本使用

### 初始化

EventLoop 使用单例模式，可以通过 `getInstance()` 方法获取实例，或者直接使用静态方法：

```php
use Nova\Fibers\Core\EventLoop;

// 获取实例
$eventLoop = EventLoop::getInstance();

// 或者直接使用静态方法（推荐）
EventLoop::defer(function() {
    echo "Deferred execution\n";
});
```

### 运行事件循环

要启动事件循环，使用 `run()` 方法：

```php
use Nova\Fibers\Core\EventLoop;

// 添加一些任务
EventLoop::defer(function() {
    echo "Deferred task\n";
});

// 启动事件循环
EventLoop::run();
```

要停止事件循环，使用 `stop()` 方法：

```php
use Nova\Fibers\Core\EventLoop;

EventLoop::defer(function() {
    echo "Stopping event loop\n";
    EventLoop::stop(); // 停止事件循环
});

EventLoop::run();
```

## 功能详解

### 1. Defer 延迟执行

`defer` 方法将在事件循环的下一次迭代中执行回调函数：

```php
use Nova\Fibers\Core\EventLoop;

echo "1. Before defer\n";

EventLoop::defer(function() {
    echo "3. Deferred execution\n";
});

echo "2. After defer\n";

// 运行事件循环以处理defer队列
EventLoop::run();
```

输出：
```
1. Before defer
2. After defer
3. Deferred execution
```

### 2. Delay 延迟定时器

`delay` 方法在指定的秒数后执行回调函数：

```php
use Nova\Fibers\Core\EventLoop;

echo "Start\n";

EventLoop::delay(1.5, function() {
    echo "Executed after 1.5 seconds\n";
});

EventLoop::run();
```

`delay` 方法返回一个定时器ID，可用于取消定时器：

```php
use Nova\Fibers\Core\EventLoop;

$timerId = EventLoop::delay(5.0, function() {
    echo "This will not be executed\n";
});

// 取消定时器
EventLoop::cancel($timerId);
```

### 3. Repeat 重复定时器

`repeat` 方法以指定的间隔重复执行回调函数：

```php
use Nova\Fibers\Core\EventLoop;

$count = 0;

EventLoop::repeat(1.0, function() use (&$count) {
    echo "Repeated execution #" . ++$count . "\n";
    
    if ($count >= 3) {
        EventLoop::stop(); // 停止事件循环
    }
});

EventLoop::run();
```

与 `delay` 类似，`repeat` 方法也返回一个定时器ID，可用于取消重复定时器：

```php
use Nova\Fibers\Core\EventLoop;

$timerId = EventLoop::repeat(1.0, function() {
    echo "This will not be executed\n";
});

// 取消重复定时器
EventLoop::cancel($timerId);
```

### 4. Stream Readable 可读流

`onReadable` 方法监听流上的可读事件：

```php
use Nova\Fibers\Core\EventLoop;

// 创建一个socket连接
$socket = stream_socket_client("tcp://httpbin.org:80", $errno, $errstr, 30);
if (!$socket) {
    die("Failed to connect: $errstr ($errno)\n");
}

stream_set_blocking($socket, false);

// 发送HTTP请求
$request = "GET /json HTTP/1.1\r\nHost: httpbin.org\r\nConnection: close\r\n\r\n";
fwrite($socket, $request);

// 监听可读事件
EventLoop::onReadable($socket, function($stream) {
    $data = fread($stream, 1024);
    if ($data !== false && strlen($data) > 0) {
        echo "Received " . strlen($data) . " bytes\n";
    } else {
        echo "Connection closed\n";
        EventLoop::cancelStream($stream);
        EventLoop::stop();
    }
});

EventLoop::run();
```

### 5. Stream Writable 可写流

`onWritable` 方法监听流上的可写事件：

```php
use Nova\Fibers\Core\EventLoop;

// 创建一个socket连接
$socket = stream_socket_client("tcp://httpbin.org:80", $errno, $errstr, 30);
if (!$socket) {
    die("Failed to connect: $errstr ($errno)\n");
}

stream_set_blocking($socket, false);

// 监听可写事件
EventLoop::onWritable($socket, function($stream) {
    $request = "GET /json HTTP/1.1\r\nHost: httpbin.org\r\nConnection: close\r\n\r\n";
    $written = fwrite($stream, $request);
    if ($written !== false) {
        echo "Sent $written bytes\n";
        EventLoop::cancelStream($stream);
    }
});

EventLoop::run();
```

### 6. Signal 信号处理

`onSignal` 方法监听操作系统信号：

```php
use Nova\Fibers\Core\EventLoop;

// 监听SIGINT信号 (Ctrl+C)
EventLoop::onSignal(SIGINT, function($signal) {
    echo "Received SIGINT, stopping\n";
    EventLoop::stop();
});

// 监听SIGTERM信号
EventLoop::onSignal(SIGTERM, function($signal) {
    echo "Received SIGTERM, stopping\n";
    EventLoop::stop();
});

EventLoop::run();
```

要取消信号监听，使用 `cancelSignal` 方法：

```php
use Nova\Fibers\Core\EventLoop;

EventLoop::onSignal(SIGUSR1, function($signal) {
    echo "Received SIGUSR1\n";
});

// 取消信号监听
EventLoop::cancelSignal(SIGUSR1);
```

## 实际应用示例

### HTTP 客户端示例

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
        $parts = parse_url($url);
        if (!$parts) {
            $callback(null, new Exception("Invalid URL: $url"));
            return;
        }
        
        $host = $parts['host'];
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $path = $parts['path'] ?? '/';
        
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
    }
    EventLoop::stop();
});

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

// 运行30秒后停止
EventLoop::delay(30.0, function() {
    echo "Stopping scheduler\n";
    EventLoop::stop();
});

EventLoop::run();
```

## 最佳实践

1. **错误处理**：在回调函数中始终使用 try-catch 块来处理可能的异常，避免中断事件循环。

2. **资源清理**：使用相应的取消方法（如 `cancel()`、`cancelStream()`、`cancelSignal()`）来清理不再需要的监听器。

3. **避免阻塞**：确保回调函数不会执行长时间运行的同步操作，这会阻塞事件循环。

4. **内存管理**：及时清理已完成任务的相关数据，避免内存泄漏。

5. **测试**：编写测试用例来验证事件循环的各种功能，确保代码的可靠性。

## 注意事项

1. 事件循环会接管程序的执行流程，直到被停止或没有更多任务需要处理。

2. 在使用信号处理功能时，需要确保 PHP 的 pcntl 扩展可用。

3. 流事件处理需要将流设置为非阻塞模式。

4. 事件循环在没有任务时会短暂休眠以避免占用过多 CPU 资源。