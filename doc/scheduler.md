# Scheduler 详解

## 概述

Scheduler 是 nova/fibers 的任务调度组件，负责管理和执行各种任务。它支持本地调度和分布式调度两种模式，可以根据需要选择合适的调度方式。

## 基本概念

### 任务

任务是 Scheduler 调度的基本单位，可以是任何可执行的代码片段。任务可以有优先级、超时时间等属性。

### 调度器

调度器负责管理任务的生命周期，包括任务的提交、执行、取消和状态查询等操作。

## 使用方法

### 本地调度器

本地调度器在单个进程中运行，适用于单机应用。

```php
use Nova\Fibers\Scheduler\LocalScheduler;

$scheduler = new LocalScheduler();

// 提交任务
$taskId = $scheduler->submit(function() {
    // 任务逻辑
    return "Task result";
});

// 获取任务结果
$result = $scheduler->getResult($taskId);

// 获取任务状态
$status = $scheduler->getStatus($taskId);

// 取消任务
$scheduler->cancel($taskId);
```

### 分布式调度器

分布式调度器可以在多个节点之间分配任务，适用于分布式应用。

```php
use Nova\Fibers\Scheduler\DistributedScheduler;

$scheduler = new DistributedScheduler([
    'nodes' => [
        'node1' => '192.168.1.10:8000',
        'node2' => '192.168.1.11:8000',
        'node3' => '192.168.1.12:8000'
    ]
]);

// 提交任务到集群
$taskId = $scheduler->submitToCluster(function() {
    // 任务逻辑
    return "Task result from cluster";
});

// 获取任务结果
$result = $scheduler->getResult($taskId);
```

## 任务属性

### 优先级

任务可以设置优先级，优先级高的任务会优先执行。

```php
use Nova\Fibers\Attributes\Priority;

#[Priority(10)]
function highPriorityTask() {
    // 高优先级任务逻辑
}
```

### 超时

任务可以设置超时时间，超过指定时间未完成的任务会被取消。

```php
use Nova\Fibers\Attributes\Timeout;

#[Timeout(30)]
function timeoutTask() {
    // 可能需要较长时间的任务逻辑
}
```

### 重试

任务可以设置重试策略，在任务失败时自动重试。

```php
use Nova\Fibers\Attributes\Retry;

#[Retry(maxAttempts: 3, delay: 1000)]
function retryTask() {
    // 可能会失败的任务逻辑
}
```

## 高级用法

### 自定义调度器

您可以通过实现 `Nova\Fibers\Contracts\SchedulerInterface` 接口来创建自定义调度器。

```php
use Nova\Fibers\Contracts\SchedulerInterface;

class CustomScheduler implements SchedulerInterface
{
    public function submit(callable $task, array $options = []): string
    {
        // 实现任务提交逻辑
    }

    public function getResult(string $taskId, ?float $timeout = null)
    {
        // 实现获取任务结果逻辑
    }

    public function cancel(string $taskId): bool
    {
        // 实现取消任务逻辑
    }

    public function getStatus(string $taskId): string
    {
        // 实现获取任务状态逻辑
    }
}
```

### 任务依赖

Scheduler 支持任务依赖，可以指定任务之间的依赖关系。

```php
use Nova\Fibers\Scheduler\TaskDependency;

// 提交任务A
$taskAId = $scheduler->submit(function() {
    // 任务A逻辑
    return "Result A";
});

// 提交依赖于任务A的任务B
$taskBId = $scheduler->submit(function() use ($scheduler, $taskAId) {
    // 等待任务A完成
    $resultA = $scheduler->getResult($taskAId);
    // 任务B逻辑
    return "Result B based on " . $resultA;
}, [
    'dependencies' => [$taskAId]
]);
```

## 最佳实践

1. **合理设置任务属性**：根据任务的特点合理设置优先级、超时和重试等属性。
2. **及时清理已完成的任务**：避免任务堆积影响性能。
3. **正确处理异常**：在任务执行过程中正确处理异常，避免影响其他任务。
4. **监控任务状态**：定期监控任务的执行状态，及时发现和处理问题。

## 故障排除

### 任务未执行

检查任务是否正确提交，以及调度器是否正常运行。

### 任务超时

检查任务的超时设置是否合理，以及任务逻辑是否存在问题。

### 分布式调度失败

检查集群节点是否正常运行，以及网络连接是否正常。

## 参考资料

- [PHP Fibers RFC](https://wiki.php.net/rfc/fibers)
- [Swoole Coroutine Docs](https://www.swoole.co.uk/docs/modules/swoole-coroutine)