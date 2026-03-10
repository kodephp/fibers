# Fiber Pools

本指南将详细介绍 Kode/Fibers 中的纤程池（FiberPool）组件，包括其用法、配置选项和最佳实践。

## 什么是 Fiber Pool？

FiberPool 是一个管理 Fiber 实例的组件，它可以：

- 维护一组预先创建的 Fiber 实例以提高性能
- 限制并发执行的 Fiber 数量
- 管理任务的执行、超时和重试
- 提供任务调度和优先级支持
- 实现资源的有效利用和回收

## 为什么使用 Fiber Pool？

在生产环境中，直接使用单个 Fiber 可能会导致以下问题：

- 创建和销毁 Fiber 的开销
- 无法控制并发数量，可能导致资源耗尽
- 缺乏任务管理功能（如超时、重试等）
- 难以监控和管理

使用 FiberPool 可以解决这些问题，提供更可靠、更高效的纤程管理。

## 创建 Fiber Pool

### 基本用法

```php
use Kode\Fibers\FiberPool;

// 创建默认配置的 FiberPool
$pool = new FiberPool();

// 创建自定义配置的 FiberPool
$pool = new FiberPool([
    'size' => 32, // 纤程池大小
    'max_exec_time' => 30, // 最大执行时间（秒）
    'max_retries' => 3, // 最大重试次数
    'gc_interval' => 100, // 垃圾回收间隔（任务数）
    'onTaskStart' => fn($taskId) => logger("Task $taskId started"), // 任务开始回调
    'onTaskComplete' => fn($taskId, $result) => logger("Task $taskId completed"), // 任务完成回调
    'onTaskFailure' => fn($taskId, $error) => logger("Task $taskId failed: $error"), // 任务失败回调
    'onTaskTimeout' => fn($taskId) => logger("Task $taskId timed out") // 任务超时回调
]);
```

### 动态池大小配置

推荐将池大小设置为 CPU 核心数的 2-4 倍，以充分利用系统资源：

```php
use Kode\Fibers\FiberPool;
use Kode\Fibers\Support\CpuInfo;

// 获取 CPU 核心数
$cpuCount = CpuInfo::get();

// 创建池大小为 CPU 核心数 4 倍的 FiberPool
$pool = new FiberPool([
    'size' => $cpuCount * 4
]);
```

## 使用 Fiber Pool 执行任务

### 执行单个任务

```php
// 执行单个任务并等待结果
$result = $pool->execute(fn() => doSomeWork());

// 带超时设置的单个任务执行
$result = $pool->execute(fn() => doSomeWork(), 10); // 10秒超时
```

### 并发执行多个任务

```php
// 并发执行多个任务并等待所有任务完成
$results = $pool->concurrent([
    fn() => fetchDataFromApi('https://api1.example.com'),
    fn() => fetchDataFromApi('https://api2.example.com'),
    fn() => fetchDataFromDatabase()
]);

// 带超时设置的并发执行
$results = $pool->concurrent(
    [
        fn() => fetchDataFromApi('https://api1.example.com'),
        fn() => fetchDataFromApi('https://api2.example.com'),
        fn() => fetchDataFromDatabase()
    ],
    10 // 所有任务总超时时间为10秒
);
```

### 异步执行任务

```php
// 异步执行任务，不等待结果
$taskId = $pool->async(fn() => processDataInBackground($data));

// 稍后检查任务状态
$status = $pool->status($taskId); // 'pending', 'running', 'completed', 'failed', 'timeout'

// 获取任务结果（如果任务已完成）
if ($status === 'completed') {
    $result = $pool->result($taskId);
}
```

## 任务优先级

FiberPool 支持任务优先级，可以为不同任务设置不同的优先级：

```php
use Kode\Fibers\Task\Task;

// 创建高优先级任务
$highPriorityTask = Task::make(fn() => handleUrgentRequest(), ['priority' => 1]);

// 创建低优先级任务
$lowPriorityTask = Task::make(fn() => performRegularMaintenance(), ['priority' => 10]);

// 提交任务
$pool->submitTask($highPriorityTask);
$pool->submitTask($lowPriorityTask);
```

## 任务重试机制

FiberPool 内置了任务重试机制，可以自动重试失败的任务：

```php
// 全局设置重试次数
$pool = new FiberPool(['max_retries' => 3]);

// 为特定任务设置重试次数
$results = $pool->concurrent([
    ['task' => fn() => unstableApiCall(), 'retries' => 5], // 重试5次
    ['task' => fn() => stableDatabaseQuery(), 'retries' => 1] // 重试1次
]);

// 自定义重试条件
$pool = new FiberPool([
    'max_retries' => 3,
    'retry_condition' => fn($error) => $error instanceof NetworkException // 仅对网络异常重试
]);
```

## 监控和统计

FiberPool 提供了监控和统计功能，可以了解池的运行状态：

```php
// 获取池统计信息
$stats = $pool->stats();

// 输出统计信息
print_r($stats);
/*
Array(
    [active_fibers] => 8  // 当前活跃的纤程数
    [pending_tasks] => 3  // 等待执行的任务数
    [completed_tasks] => 156  // 已完成的任务数
    [failed_tasks] => 4  // 失败的任务数
    [timeout_tasks] => 2  // 超时的任务数
    [total_execution_time] => 345.6  // 总执行时间（毫秒）
    [average_execution_time] => 2.21  // 平均执行时间（毫秒）
)
*/

// 重置统计信息
$pool->resetStats();
```

## 最佳实践

### 池大小设置

- **Web 应用**：设置为 CPU 核心数的 2-4 倍
- **CPU 密集型任务**：设置为 CPU 核心数或略多一些
- **I/O 密集型任务**：可以设置为 CPU 核心数的 4-8 倍

### 超时设置

- 根据任务性质设置合理的超时时间
- 对于网络请求，考虑设置较短的超时时间（如 5-10 秒）
- 对于数据库查询，考虑设置适中的超时时间（如 10-30 秒）
- 对于长时间运行的任务，考虑拆分为多个短任务

### 资源管理

- 使用 `gc_interval` 定期清理资源
- 任务完成后释放不再使用的大型对象
- 避免在任务中创建过多的临时对象

### 错误处理

- 为任务设置适当的重试策略
- 使用自定义的 `retry_condition` 仅对可恢复的错误进行重试
- 实现任务失败和超时的回调函数，记录详细日志

## 常见问题

### Q: 为什么我的任务超时了？

A: 任务超时可能有多种原因，包括：
- 任务执行时间确实超过了超时设置
- 系统资源不足，导致任务排队等待时间过长
- 任务中包含阻塞操作，阻止了其他任务的执行
- 死锁或活锁

### Q: 如何确定合适的池大小？

A: 池大小没有一刀切的标准答案，需要根据具体的应用场景和系统资源进行调整。建议从小规模开始，通过性能测试找到最佳值。

### Q: 我可以在一个应用中使用多个 FiberPool 吗？

A: 是的，您可以创建多个具有不同配置的 FiberPool 实例，用于处理不同类型的任务。例如，一个池用于处理网络请求，另一个池用于处理数据库查询。

## 高级用法

### 自定义任务类

您可以创建自定义任务类，实现 `Kode\Fibers\Contracts\Runnable` 接口：

```php
use Kode\Fibers\Contracts\Runnable;

class DataProcessor implements Runnable
{
    private $data;
    private $options;

    public function __construct($data, array $options = [])
    {
        $this->data = $data;
        $this->options = $options;
    }

    public function run()
    {
        // 处理数据的逻辑
        return processData($this->data, $this->options);
    }
}

// 使用自定义任务类
$pool = new FiberPool();
$result = $pool->execute(new DataProcessor($data, ['format' => 'json']));
```

### 动态调整池大小

在某些情况下，您可能需要动态调整池的大小：

```php
$pool = new FiberPool(['size' => 16]);

// 稍后根据负载调整池大小
if ($systemLoad > 0.8) {
    $pool->resize(32); // 增加池大小
}

if ($systemLoad < 0.3 && $pool->stats()['active_fibers'] < 8) {
    $pool->resize(8); // 减少池大小
}
```

## 下一步

- 查看 [Channels](channels.md) 文档了解如何在纤程之间进行通信
- 查看 [框架集成](framework-integration.md) 文档了解如何在不同框架中使用 FiberPool
- 查看 [高级示例](../examples/advanced_example.php) 了解更多 FiberPool 的高级用法