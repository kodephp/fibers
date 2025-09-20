# Scheduler 调度器开发指南

## 概述

Scheduler 是 nova/fibers 包中的任务调度组件，它基于事件循环和 Fiber 实现了一个高效的任务调度系统。它支持任务的提交、执行、结果获取和状态管理等功能。

## 核心概念

### 调度器架构

Scheduler 采用分层架构设计：

1. **Scheduler 层** - 基础调度器，管理任务队列和事件循环
2. **LocalScheduler 层** - 本地调度器实现，提供任务管理功能
3. **FiberPool 层** - Fiber 池，负责实际的任务执行

### 任务生命周期

任务在调度器中的生命周期包括以下几个阶段：

1. **Pending** - 任务已提交但尚未开始执行
2. **Running** - 任务正在执行中
3. **Completed** - 任务已完成并返回结果
4. **Failed** - 任务执行失败
5. **Cancelled** - 任务已被取消

## 详细使用指南

### 1. Scheduler 基础使用

Scheduler 是基础的调度器类，它使用事件循环来管理任务。

```php
use Nova\Fibers\Core\Scheduler;

// 创建调度器实例
$scheduler = new Scheduler();

// 添加任务
$taskId = $scheduler->addTask(function() {
    // 模拟一些工作
    usleep(100000); // 100ms
    return "Task completed";
});

// 获取任务队列
$taskQueue = $scheduler->getTaskQueue();

// 获取活跃 Fiber 数量
$activeCount = $scheduler->getActiveFiberCount();

// 停止调度器
$scheduler->stop();
```

### 2. LocalScheduler 详细使用

LocalScheduler 是分布式调度器接口的本地实现，提供了完整的任务管理功能。

```php
use Nova\Fibers\Scheduler\LocalScheduler;
use Nova\Fibers\Context\Context;

// 创建本地调度器
$scheduler = new LocalScheduler([
    'size' => 8,        // Fiber 池大小
    'timeout' => 30,    // 任务超时时间
    'max_retries' => 3  // 最大重试次数
]);

// 提交任务
$taskId = $scheduler->submit(function() {
    // 执行任务逻辑
    return "Task result";
});

// 提交带上下文的任务
$context = new Context('task_context');
$context = $context->withValue('user_id', 123);

$taskId = $scheduler->submit(function() {
    // 在任务中可以访问上下文信息
    return "Task with context";
}, $context);

// 获取任务结果
try {
    $result = $scheduler->getResult($taskId, 5.0); // 5秒超时
    echo "Result: " . $result . "\n";
} catch (Exception $e) {
    echo "Task failed: " . $e->getMessage() . "\n";
}

// 获取任务状态
$status = $scheduler->getStatus($taskId);
echo "Task status: " . $status . "\n";

// 取消任务
if ($scheduler->cancel($taskId)) {
    echo "Task cancelled\n";
}

// 获取集群信息
$clusterInfo = $scheduler->getClusterInfo();
print_r($clusterInfo);
```

## 高级用例示例

### 1. 批量任务处理器

```php
use Nova\Fibers\Scheduler\LocalScheduler;

class BatchTaskProcessor 
{
    private LocalScheduler $scheduler;
    
    public function __construct(array $config = []) 
    {
        $this->scheduler = new LocalScheduler($config);
    }
    
    public function processBatch(array $tasks): array 
    {
        $taskIds = [];
        $results = [];
        
        // 提交所有任务
        foreach ($tasks as $index => $task) {
            $taskId = $this->scheduler->submit($task);
            $taskIds[$taskId] = $index;
        }
        
        // 收集结果
        foreach ($taskIds as $taskId => $index) {
            try {
                $results[$index] = $this->scheduler->getResult($taskId, 10.0);
            } catch (Exception $e) {
                $results[$index] = [
                    'error' => true,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}

// 使用示例
$processor = new BatchTaskProcessor([
    'size' => 16,
    'timeout' => 60
]);

$tasks = [
    function() { 
        usleep(100000); 
        return "Task 1 completed"; 
    },
    function() { 
        usleep(200000); 
        return "Task 2 completed"; 
    },
    function() { 
        throw new Exception("Task 3 failed"); 
    }
];

$results = $processor->processBatch($tasks);

foreach ($results as $index => $result) {
    if (is_array($result) && isset($result['error'])) {
        echo "Task $index failed: " . $result['message'] . "\n";
    } else {
        echo "Task $index result: $result\n";
    }
}
```

### 2. 异步数据库操作

```php
use Nova\Fibers\Scheduler\LocalScheduler;

class AsyncDatabase 
{
    private LocalScheduler $scheduler;
    private PDO $pdo;
    
    public function __construct(PDO $pdo, array $config = []) 
    {
        $this->pdo = $pdo;
        $this->scheduler = new LocalScheduler($config);
    }
    
    public function select(string $sql, array $params = []): array 
    {
        $taskId = $this->scheduler->submit(function() use ($sql, $params) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
        
        return $this->scheduler->getResult($taskId);
    }
    
    public function insert(string $sql, array $params = []): int 
    {
        $taskId = $this->scheduler->submit(function() use ($sql, $params) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $this->pdo->lastInsertId();
        });
        
        return (int) $this->scheduler->getResult($taskId);
    }
    
    public function update(string $sql, array $params = []): int 
    {
        $taskId = $this->scheduler->submit(function() use ($sql, $params) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        });
        
        return $this->scheduler->getResult($taskId);
    }
    
    public function batchSelect(array $queries): array 
    {
        $tasks = [];
        foreach ($queries as $query) {
            $tasks[] = function() use ($query) {
                $stmt = $this->pdo->prepare($query['sql']);
                $stmt->execute($query['params'] ?? []);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            };
        }
        
        $taskIds = [];
        foreach ($tasks as $task) {
            $taskIds[] = $this->scheduler->submit($task);
        }
        
        $results = [];
        foreach ($taskIds as $taskId) {
            $results[] = $this->scheduler->getResult($taskId);
        }
        
        return $results;
    }
}

// 使用示例
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 创建表
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)");
    
    $db = new AsyncDatabase($pdo, ['size' => 4]);
    
    // 插入数据
    $userId = $db->insert(
        "INSERT INTO users (name, email) VALUES (?, ?)",
        ['John Doe', 'john@example.com']
    );
    echo "Inserted user with ID: $userId\n";
    
    // 查询数据
    $users = $db->select("SELECT * FROM users WHERE id = ?", [$userId]);
    print_r($users);
    
    // 批量查询
    $queries = [
        ['sql' => "SELECT COUNT(*) as count FROM users"],
        ['sql' => "SELECT * FROM users LIMIT 1"]
    ];
    
    $results = $db->batchSelect($queries);
    echo "User count: " . $results[0][0]['count'] . "\n";
    echo "First user: " . $results[1][0]['name'] . "\n";
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
```

### 3. Web 服务任务调度

```php
use Nova\Fibers\Scheduler\LocalScheduler;

class WebServiceScheduler 
{
    private LocalScheduler $scheduler;
    
    public function __construct(array $config = []) 
    {
        $this->scheduler = new LocalScheduler($config);
    }
    
    public function scheduleHttpRequest(string $url, string $method = 'GET', array $options = []): string 
    {
        return $this->scheduler->submit(function() use ($url, $method, $options) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout'] ?? 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if (isset($options['data'])) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $options['data']);
                }
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL error: $error");
            }
            
            if ($httpCode >= 400) {
                throw new Exception("HTTP error: $httpCode");
            }
            
            return [
                'status_code' => $httpCode,
                'body' => $response
            ];
        });
    }
    
    public function getTaskResult(string $taskId, ?float $timeout = null) 
    {
        return $this->scheduler->getResult($taskId, $timeout);
    }
    
    public function getTaskStatus(string $taskId): string 
    {
        return $this->scheduler->getStatus($taskId);
    }
    
    public function cancelTask(string $taskId): bool 
    {
        return $this->scheduler->cancel($taskId);
    }
}

// 使用示例
$scheduler = new WebServiceScheduler(['size' => 8]);

// 调度多个HTTP请求
$taskIds = [];
$urls = [
    'https://httpbin.org/delay/1',
    'https://httpbin.org/delay/2',
    'https://httpbin.org/json'
];

foreach ($urls as $url) {
    $taskId = $scheduler->scheduleHttpRequest($url);
    $taskIds[] = $taskId;
    echo "Scheduled request to $url with task ID: $taskId\n";
}

// 获取结果
foreach ($taskIds as $taskId) {
    try {
        $result = $scheduler->getTaskResult($taskId, 10.0);
        echo "Task $taskId completed with status: " . $result['status_code'] . "\n";
    } catch (Exception $e) {
        echo "Task $taskId failed: " . $e->getMessage() . "\n";
    }
}
```

## 最佳实践

### 1. 合理配置并发数

根据系统资源和任务特性合理设置 Fiber 池大小：

```php
// I/O 密集型任务 - 可以设置较高的并发数
$scheduler = new LocalScheduler([
    'size' => 32
]);

// CPU 密集型任务 - 应该设置较低的并发数
$scheduler = new LocalScheduler([
    'size' => 4
]);
```

### 2. 设置合适的超时时间

为任务执行和结果获取设置合理的超时时间：

```php
$taskId = $scheduler->submit(function() {
    // 任务逻辑
    return "result";
});

// 设置5秒超时
$result = $scheduler->getResult($taskId, 5.0);
```

### 3. 错误处理

始终使用 try-catch 块处理 `getResult` 方法可能抛出的异常：

```php
try {
    $result = $scheduler->getResult($taskId);
    // 处理结果
} catch (Exception $e) {
    // 处理错误
    error_log("Task failed: " . $e->getMessage());
}
```

### 4. 资源管理

及时处理已完成任务的结果，避免内存泄漏：

```php
// 定期清理已完成的任务数据
$cleanupTask = $scheduler->submit(function() use (&$taskResults) {
    // 清理旧的结果数据
    if (count($taskResults) > 1000) {
        $taskResults = array_slice($taskResults, -100);
    }
});

// 每分钟执行一次清理
$scheduler->submit(function() use ($cleanupTask) {
    // 定时执行清理任务
}, null, ['repeat' => 60]);
```

### 5. 状态监控

在关键节点检查任务状态，确保任务按预期执行：

```php
$taskId = $scheduler->submit(function() {
    // 长时间运行的任务
    for ($i = 0; $i < 100; $i++) {
        // 执行工作
        usleep(100000);
    }
    return "completed";
});

// 定期检查任务状态
$statusCheckTask = $scheduler->submit(function() use ($taskId, $scheduler) {
    $status = $scheduler->getStatus($taskId);
    echo "Task status: $status\n";
}, null, ['repeat' => 5]); // 每5秒检查一次
```

## API 参考

### Scheduler 类

| 方法 | 描述 |
|------|------|
| `__construct()` | 创建调度器实例 |
| `addTask(callable $task): string` | 添加任务到调度器 |
| `run(): void` | 运行调度器 |
| `stop(): void` | 停止调度器 |
| `getTaskQueue(): Channel` | 获取任务队列 |
| `getActiveFiberCount(): int` | 获取活跃 Fiber 数量 |

### LocalScheduler 类

| 方法 | 描述 |
|------|------|
| `__construct(array $poolOptions = [])` | 创建本地调度器实例 |
| `submit(callable $task, ?Context $context = null, array $options = []): string` | 提交任务 |
| `getResult(string $taskId, ?float $timeout = null): mixed` | 获取任务结果 |
| `cancel(string $taskId): bool` | 取消任务 |
| `getStatus(string $taskId): string` | 获取任务状态 |
| `getClusterInfo(): array` | 获取集群信息 |

## 注意事项

1. `getResult` 方法是阻塞的，会等待任务完成或超时。

2. 任务一旦提交就无法真正取消，`cancel` 方法只是标记任务为已取消。

3. 任务状态包括：pending（待处理）、running（运行中）、completed（已完成）、failed（失败）、cancelled（已取消）、unknown（未知）。

4. LocalScheduler 是本地实现，不支持真正的分布式任务调度。如需分布式功能，需要实现 `DistributedSchedulerInterface` 接口。

5. 在生产环境中，应适当配置 Fiber 池大小以避免资源耗尽。

通过遵循这些指南和示例，您可以充分利用 Scheduler 的功能来构建高效的任务调度系统。