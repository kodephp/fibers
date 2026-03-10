# 任务管理

本指南将详细介绍 Kode/Fibers 中的任务管理功能，包括任务创建、执行、调度和监控。

## 任务基础

任务（Task）是在 Fiber 中执行的工作单元。Kode/Fibers 提供了强大的任务管理系统，可以处理各种复杂的执行场景。

### 任务类型

Kode/Fibers 支持多种任务类型：

1. **闭包任务**：最常见的任务类型，使用 PHP 闭包函数
2. **可运行对象**：实现 `Runnable` 接口的对象
3. **重试任务**：支持自动重试的任务
4. **优先级任务**：带有执行优先级的任务

## 创建任务

### 基本任务创建

```php
use Kode\Fibers\Task\Task;

// 创建基本任务
$task = Task::make(fn() => doSomeWork());

// 创建带配置的任务
$task = Task::make(
    fn() => fetchData($url),
    [
        'id' => 'fetch-data-123',
        'timeout' => 10, // 10秒
        'priority' => 5, // 优先级（值越小优先级越高）
        'retries' => 3, // 重试次数
        'context' => ['user_id' => 123] // 上下文数据
    ]
);
```

### Runnable 接口实现

对于更复杂的任务，可以实现 `Runnable` 接口：

```php
use Kode\Fibers\Contracts\Runnable;
use Kode\Fibers\Context\Context;

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
        // 可以访问上下文数据
        $userId = Context::get('user_id');
        
        // 处理数据
        return processData($this->data, $this->options, $userId);
    }
}

// 创建可运行对象任务
$task = Task::make(new DataProcessor($data, ['format' => 'json']));
```

### 重试任务

对于可能失败但需要重试的操作，可以使用重试任务：

```php
use Kode\Fibers\Task\RetryableTask;

// 创建重试任务
$task = RetryableTask::make(
    fn() => unstableApiCall(), // 可能失败的操作
    3, // 最多重试3次
    fn($error, $attempt) => $error instanceof NetworkException && $attempt < 3 // 自定义重试条件
);

// 使用简便方法
$task = Task::make(fn() => unstableApiCall())->withRetries(3);
```

## 执行任务

### 单个任务执行

```php
use Kode\Fibers\Task\TaskRunner;

// 执行单个任务
$task = Task::make(fn() => doSomeWork());
$result = TaskRunner::run($task);

// 带超时执行
$result = TaskRunner::run($task, 10); // 10秒超时

// 使用简便方法
$result = Task::run(fn() => doSomeWork(), 10);
```

### 并发任务执行

```php
// 并发执行多个任务
$tasks = [
    Task::make(fn() => fetchData('https://api1.example.com')),
    Task::make(fn() => fetchData('https://api2.example.com')),
    Task::make(fn() => fetchFromDatabase())
];

$results = TaskRunner::concurrent($tasks);

// 带整体超时
$results = TaskRunner::concurrent($tasks, 30); // 所有任务总超时30秒
```

### 使用纤程池执行任务

对于生产环境，推荐使用纤程池执行任务：

```php
use Kode\Fibers\FiberPool;

$pool = new FiberPool(['size' => 16]);

// 执行单个任务
$result = $pool->execute(fn() => doSomeWork());

// 并发执行多个任务
$results = $pool->concurrent([
    fn() => fetchData('https://api1.example.com'),
    fn() => fetchData('https://api2.example.com')
]);

// 异步执行任务（不等待结果）
$taskId = $pool->async(fn() => processInBackground($data));

// 稍后检查任务状态
$status = $pool->status($taskId); // 'pending', 'running', 'completed', 'failed', 'timeout'

// 获取任务结果
if ($status === 'completed') {
    $result = $pool->result($taskId);
}
```

## 任务队列

Kode/Fibers 提供了任务队列功能，可以管理待执行的任务：

```php
use Kode\Fibers\Task\TaskQueue;

// 创建任务队列
$queue = new TaskQueue();

// 添加任务到队列
$queue->push(Task::make(fn() => doWork1()));
$queue->push(Task::make(fn() => doWork2()));
$queue->push(Task::make(fn() => doWork3()));

// 按顺序执行队列中的所有任务
$results = $queue->processAll();

// 执行单个任务
$task = $queue->pop();
$result = TaskRunner::run($task);
```

### 优先级队列

```php
// 创建优先级队列
$queue = new TaskQueue(true); // true 表示启用优先级

// 添加带优先级的任务
$queue->push(Task::make(fn() => urgentWork())->withPriority(1)); // 高优先级
$queue->push(Task::make(fn() => normalWork())->withPriority(5)); // 普通优先级
$queue->push(Task::make(fn() => lowPriorityWork())->withPriority(10)); // 低优先级

// 按优先级执行任务
while ($task = $queue->pop()) {
    TaskRunner::run($task);
}
```

## 任务上下文

任务上下文允许在任务执行过程中传递数据：

```php
use Kode\Fibers\Context\Context;

// 设置上下文数据
Context::set('request_id', 'abc-123');
Context::set('user', ['id' => 123, 'name' => 'User Name']);

// 在任务中访问上下文数据
$task = Task::make(function() {
    $requestId = Context::get('request_id');
    $user = Context::get('user');
    
    // 使用上下文数据
    logWithRequestId($requestId, "Processing task for user: {$user['name']}");
    
    return doSomeWork($user['id']);
});

// 执行任务
TaskRunner::run($task);

// 清除上下文
Context::clear();
```

### 上下文隔离

每个 Fiber 都有自己独立的上下文，不会相互干扰：

```php
use Kode\Fibers\Facades\Fiber;

// 在第一个 Fiber 中设置上下文
Fiber::run(function() {
    Context::set('value', 'Fiber 1');
    
    // 这个值只会在当前 Fiber 中可见
    sleep(2);
    echo "Fiber 1: " . Context::get('value') . "\n"; // 输出: Fiber 1
});

// 在第二个 Fiber 中设置不同的上下文
Fiber::run(function() {
    Context::set('value', 'Fiber 2');
    
    // 这个值只会在当前 Fiber 中可见
    sleep(1);
    echo "Fiber 2: " . Context::get('value') . "\n"; // 输出: Fiber 2
});
```

## 任务超时控制

Kode/Fibers 提供了多种方式来控制任务的执行时间：

### 基本超时设置

```php
// 创建带超时的任务
$task = Task::make(fn() => longRunningOperation())->withTimeout(10); // 10秒

// 执行任务，超时会抛出 TimeoutException
try {
    $result = TaskRunner::run($task);
} catch (\Kode\Fibers\Exceptions\TimeoutException $e) {
    echo "Task timed out after 10 seconds\n";
}
```

### 超时回调

```php
// 设置超时回调
$task = Task::make(fn() => longRunningOperation())
    ->withTimeout(10)
    ->withTimeoutCallback(function() {
        logError("Task timed out, cleaning up resources...");
        cleanupResources();
    });
```

### 进度报告

对于长时间运行的任务，可以实现进度报告：

```php
use Kode\Fibers\Task\ProgressReporter;

$reporter = new ProgressReporter();

// 监听进度更新
$reporter->onProgress(function($progress) {
    echo "Progress: {$progress}%\n";
});

// 在任务中报告进度
$task = Task::make(function() use ($reporter) {
    for ($i = 0; $i < 10; $i++) {
        doSomeWork();
        $reporter->report(($i + 1) * 10); // 报告进度百分比
    }
});

// 在另一个 Fiber 中执行任务
Fiber::run(fn() => TaskRunner::run($task));

// 主线程可以继续做其他事情，同时接收进度更新
```

## 任务监控与统计

### 任务执行统计

```php
use Kode\Fibers\Task\TaskStats;

// 启用全局统计
TaskStats::enable();

// 执行一些任务
TaskRunner::run(Task::make(fn() => doSomeWork()));
TaskRunner::concurrent([...]);

// 获取统计信息
$stats = TaskStats::get();

print_r($stats);
/*
Array(
    [total_tasks] => 100
    [completed_tasks] => 95
    [failed_tasks] => 3
    [timeout_tasks] => 2
    [total_execution_time] => 456.78
    [average_execution_time] => 4.57
    [max_execution_time] => 12.34
    [min_execution_time] => 0.12
)
*/

// 重置统计信息
TaskStats::reset();
```

### 任务执行事件

可以监听任务执行的各个阶段：

```php
use Kode\Fibers\Event\EventBus;

// 监听任务开始事件
EventBus::on('task.start', function($event) {
    $taskId = $event->task->getId();
    logDebug("Task $taskId started");
});

// 监听任务完成事件
EventBus::on('task.complete', function($event) {
    $taskId = $event->task->getId();
    $result = $event->result;
    logDebug("Task $taskId completed successfully");
});

// 监听任务失败事件
EventBus::on('task.fail', function($event) {
    $taskId = $event->task->getId();
    $error = $event->error;
    logError("Task $taskId failed: " . $error->getMessage());
});

// 监听任务超时事件
EventBus::on('task.timeout', function($event) {
    $taskId = $event->task->getId();
    logWarning("Task $taskId timed out");
});
```

## 高级任务模式

### 任务链

任务链允许按顺序执行多个任务，每个任务的结果作为下一个任务的输入：

```php
use Kode\Fibers\Task\TaskChain;

// 创建任务链
$chain = TaskChain::make()
    ->add(fn($input = null) => fetchData($input))
    ->add(fn($data) => processData($data))
    ->add(fn($processedData) => saveData($processedData))
    ->onError(fn($error, $taskIndex) => handleChainError($error, $taskIndex));

// 执行任务链
$result = $chain->execute('initial-input');
```

### 任务分支

对于需要根据条件执行不同任务的场景，可以使用任务分支：

```php
use Kode\Fibers\Task\TaskBranch;

// 创建任务分支
$branch = TaskBranch::make()
    ->addCondition(fn($input) => $input['type'] === 'user', fn($input) => handleUser($input))
    ->addCondition(fn($input) => $input['type'] === 'product', fn($input) => handleProduct($input))
    ->setDefault(fn($input) => handleDefault($input));

// 执行任务分支
$result1 = $branch->execute(['type' => 'user', 'data' => $userData]);
$result2 = $branch->execute(['type' => 'product', 'data' => $productData]);
$result3 = $branch->execute(['type' => 'unknown', 'data' => $unknownData]);
```

### 任务组合

任务组合允许将多个任务组合成一个更大的任务：

```php
use Kode\Fibers\Task\TaskComposer;

// 创建任务组合
$composer = new TaskComposer();

// 添加并行任务
$composer->parallel([
    'user_data' => fn() => fetchUserData($userId),
    'user_orders' => fn() => fetchUserOrders($userId),
    'user_preferences' => fn() => fetchUserPreferences($userId)
]);

// 添加顺序任务，依赖前面的结果
$composer->sequential(fn($results) => 
    compileUserProfile(
        $results['user_data'],
        $results['user_orders'],
        $results['user_preferences']
    )
);

// 执行组合任务
$userProfile = $composer->execute();
```

## 最佳实践

### 任务设计原则

1. **任务应该小而专注**：每个任务只做一件事，并做好
2. **任务应该是幂等的**：相同的输入应该产生相同的输出，且多次执行不会产生副作用
3. **任务应该是可序列化的**：便于存储和恢复
4. **任务应该包含足够的上下文**：便于日志记录和调试
5. **任务应该设置合理的超时**：避免长时间占用资源

### 错误处理策略

1. **明确区分可重试和不可重试的错误**：网络错误通常可以重试，而业务逻辑错误通常不应该重试
2. **使用指数退避重试**：失败后逐渐增加重试间隔
3. **设置最大重试次数**：避免无限重试
4. **记录失败原因**：便于调试和分析
5. **实现熔断机制**：连续失败次数过多时暂停重试

```php
// 指数退避重试示例
$task = RetryableTask::make(
    fn() => unstableOperation(),
    5, // 最多重试5次
    fn($error, $attempt) => $error instanceof RetryableException,
    fn($attempt) => min(10, pow(2, $attempt - 1)) // 1, 2, 4, 8, 10秒
);
```

### 资源管理

1. **使用 try/finally 确保资源释放**：

```php
$task = Task::make(function() {
    $resource = acquireResource();
    try {
        return useResource($resource);
    } finally {
        releaseResource($resource);
    }
});
```

2. **避免在任务中持有全局资源**：

```php
// 不好的做法
$db = new PDO(...);
$task = Task::make(function() use ($db) {
    // 使用全局数据库连接
});

// 好的做法
$task = Task::make(function() {
    $db = new PDO(...); // 每个任务创建自己的连接
    try {
        return useDatabase($db);
    } finally {
        $db = null; // 释放连接
    }
});
```

3. **使用连接池**：对于数据库等资源密集型连接，考虑使用连接池

```php
use Kode\Fibers\Support\ConnectionPool;

// 创建数据库连接池
$dbPool = new ConnectionPool(
    function() { return new PDO(...); }, // 创建连接的工厂函数
    10 // 池大小
);

// 在任务中使用连接池
$task = Task::make(function() use ($dbPool) {
    $db = $dbPool->acquire(); // 获取连接
    try {
        return queryDatabase($db);
    } finally {
        $dbPool->release($db); // 释放连接回池
    }
});
```

## 常见问题

### Q: 任务执行超时后会发生什么？

A: 当任务执行超时时，会抛出 `Kode\Fibers\Exceptions\TimeoutException` 异常，并且正在执行的 Fiber 会被终止。如果设置了超时回调，回调函数会在 Fiber 终止前执行，用于清理资源。

### Q: 如何处理任务之间的依赖关系？

A: 有几种方法可以处理任务依赖：

1. 使用 `TaskChain` 按顺序执行任务，将一个任务的结果传递给下一个任务
2. 使用 `TaskComposer` 组合并行和顺序任务
3. 手动管理依赖，先执行依赖任务，然后将结果传递给后续任务

### Q: 任务可以在不同的进程中执行吗？

A: Kode/Fibers 主要关注的是在单个进程内使用 Fiber 实现并发。如果需要在多个进程中执行任务，可以结合 PHP 的多进程扩展（如 pcntl）或使用消息队列（如 RabbitMQ、Redis）将任务分发到多个进程。

### Q: 如何限制任务的资源使用？

A: 目前 PHP 没有提供直接限制单个 Fiber 资源使用的机制。但可以通过以下方式间接限制：

1. 设置合理的超时时间
2. 在任务中定期检查资源使用情况
3. 使用操作系统级别的资源限制（如 ulimit）
4. 对于内存密集型任务，考虑在任务执行前后检查内存使用

```php
$task = Task::make(function() {
    $startMemory = memory_get_usage();
    
    try {
        $result = doMemoryIntensiveWork();
        
        $endMemory = memory_get_usage();
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB
        
        if ($memoryUsed > 100) { // 如果使用了超过100MB内存
            logWarning("Task used {$memoryUsed}MB of memory");
        }
        
        return $result;
    } finally {
        // 尝试释放未使用的内存
        gc_collect_cycles();
    }
});
```

## 下一步

- 查看 [环境检测](environment-checks.md) 文档了解如何检测和处理环境限制
- 查看 [最佳实践](best-practices.md) 文档了解更多使用建议
- 查看 [API 参考](api-reference.md) 文档了解完整的 API