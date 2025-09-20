# FiberPool 详解

## 概述

FiberPool 是 nova/fibers 的核心组件之一，它提供了一个高性能的纤程池实现，用于管理和复用纤程资源。通过 FiberPool，可以有效地控制并发数量，避免创建过多纤程导致的资源消耗。

## 基本概念

### 纤程池

纤程池是一个预先创建并维护的纤程集合，当需要执行任务时，可以从池中获取纤程来执行，执行完成后纤程会返回池中等待下次使用。

### 池化优势

1. **资源复用**：避免频繁创建和销毁纤程的开销
2. **并发控制**：限制同时运行的纤程数量
3. **性能提升**：减少内存分配和上下文切换的开销

## 使用方法

### 基本使用

```php
use Nova\Fibers\FiberPool;

// 创建纤程池
$pool = new FiberPool([
    'size' => 16, // 池大小
    'timeout' => 30 // 任务超时时间（秒）
]);

// 提交单个任务
$result = $pool->submit(function() {
    // 模拟耗时操作
    usleep(100000); // 0.1秒
    return "Task completed";
});

echo $result; // 输出: Task completed
```

### 并发执行多个任务

```php
use Nova\Fibers\FiberPool;

$pool = new FiberPool(['size' => 8]);

// 定义多个任务
$tasks = [
    function() { return "Task 1 result"; },
    function() { return "Task 2 result"; },
    function() { return "Task 3 result"; },
    // ... 更多任务
];

// 并发执行所有任务
$results = $pool->concurrent($tasks);

print_r($results);
// 输出:
// Array
// (
//     [0] => Task 1 result
//     [1] => Task 2 result
//     [2] => Task 3 result
// )
```

### 带超时的任务执行

```php
use Nova\Fibers\FiberPool;

$pool = new FiberPool(['size' => 4]);

try {
    $result = $pool->submit(function() {
        sleep(5); // 模拟长时间运行的任务
        return "Completed";
    }, 3); // 设置3秒超时
    
    echo $result;
} catch (Exception $e) {
    echo "Task timed out: " . $e->getMessage();
}
```

## 配置选项

### 池大小 (size)

控制纤程池中纤程的数量，默认值为 CPU 核心数的 4 倍。

```php
use Nova\Fibers\Support\CpuInfo;

$pool = new FiberPool([
    'size' => CpuInfo::get() * 2 // 设置为 CPU 核心数的 2 倍
]);
```

### 任务超时 (timeout)

设置任务的最大执行时间，超时后任务会被取消。

```php
$pool = new FiberPool([
    'timeout' => 60 // 60秒超时
]);
```

### 最大重试次数 (max_retries)

设置任务失败时的最大重试次数。

```php
$pool = new FiberPool([
    'max_retries' => 3 // 最多重试3次
]);
```

### 重试延迟 (retry_delay)

设置重试之间的延迟时间（毫秒）。

```php
$pool = new FiberPool([
    'retry_delay' => 1000 // 重试延迟1秒
]);
```

## 高级用法

### 自定义纤程池

可以通过实现 `Nova\Fibers\Contracts\PoolInterface` 接口来创建自定义纤程池。

```php
use Nova\Fibers\Contracts\PoolInterface;

class CustomFiberPool implements PoolInterface
{
    public function submit(callable $task, ?float $timeout = null)
    {
        // 实现任务提交逻辑
    }
    
    public function concurrent(array $tasks, ?float $timeout = null): array
    {
        // 实现并发执行逻辑
    }
    
    public function getSize(): int
    {
        // 返回池大小
    }
    
    public function getActiveCount(): int
    {
        // 返回活跃纤程数量
    }
}
```

### 任务回调

可以为任务设置执行前后的回调函数。

```php
use Nova\Fibers\FiberPool;

$pool = new FiberPool(['size' => 4]);

$taskId = $pool->submit(function() {
    return "Task result";
}, null, [
    'onStart' => function() {
        echo "Task started\n";
    },
    'onComplete' => function($result) {
        echo "Task completed with result: $result\n";
    },
    'onError' => function($exception) {
        echo "Task failed: " . $exception->getMessage() . "\n";
    }
]);
```

### 池监控

FiberPool 提供了监控功能，可以获取池的状态信息。

```php
use Nova\Fibers\FiberPool;

$pool = new FiberPool(['size' => 8]);

// 获取池状态信息
$stats = $pool->getStats();
print_r($stats);

// 输出示例:
// Array
// (
//     [size] => 8
//     [active] => 2
//     [idle] => 6
//     [total_submitted] => 15
//     [total_completed] => 13
//     [total_failed] => 2
// )
```

## 最佳实践

1. **合理设置池大小**：根据系统资源和任务特性合理设置纤程池大小，通常建议设置为 CPU 核心数的 2-4 倍。
2. **设置合适的超时时间**：为任务执行设置合理的超时时间，避免无限等待。
3. **异常处理**：始终使用 try-catch 块处理任务执行可能抛出的异常。
4. **资源清理**：在应用结束时调用 `shutdown()` 方法清理纤程池资源。
5. **监控池状态**：定期监控纤程池的状态，及时发现和处理性能问题。

## 故障排除

### 任务执行缓慢

检查池大小是否设置过小，或者任务本身是否存在性能问题。

### 内存泄漏

确保任务执行完成后正确释放资源，避免循环引用。

### 纤程阻塞

检查任务中是否使用了阻塞操作，应使用非阻塞的异步操作替代。

## 参考资料

- [PHP Fibers RFC](https://wiki.php.net/rfc/fibers)
- [Swoole Coroutine Docs](https://www.swoole.co.uk/docs/modules/swoole-coroutine)
- [Swow Fiber Guide](https://docs.swow.io/)